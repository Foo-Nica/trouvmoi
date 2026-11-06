"""Stockage des empreintes faciales (SQLite) et recherche par similarité.

Chaque événement est une collection isolée : une recherche ne sort jamais de
l'événement demandé. Les vecteurs d'un événement sont chargés en mémoire sous
forme de matrice ; une recherche est un produit matriciel (quelques millisecondes
pour plusieurs centaines de milliers de visages).
"""

from __future__ import annotations

import sqlite3
import threading
import uuid
from collections import OrderedDict
from dataclasses import dataclass
from datetime import UTC, datetime
from pathlib import Path

import numpy as np

from .engine import EMBEDDING_DIM, DetectedFace

SCHEMA = """
CREATE TABLE IF NOT EXISTS faces (
    id TEXT PRIMARY KEY,
    event_id TEXT NOT NULL,
    photo_id TEXT NOT NULL,
    x INTEGER NOT NULL, y INTEGER NOT NULL, w INTEGER NOT NULL, h INTEGER NOT NULL,
    det_score REAL NOT NULL,
    sharpness REAL NOT NULL,
    embedding BLOB NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_faces_event ON faces(event_id);
CREATE INDEX IF NOT EXISTS idx_faces_event_photo ON faces(event_id, photo_id);
CREATE TABLE IF NOT EXISTS event_versions (
    event_id TEXT PRIMARY KEY,
    version INTEGER NOT NULL
);
"""


@dataclass(frozen=True)
class StoredFace:
    id: str
    photo_id: str
    x: int
    y: int
    w: int
    h: int
    det_score: float
    sharpness: float


@dataclass(frozen=True)
class Match:
    photo_id: str
    face_id: str
    similarity: float


@dataclass
class _EventMatrix:
    version: int
    face_ids: np.ndarray
    photo_ids: np.ndarray
    vectors: np.ndarray


class FaceStore:
    def __init__(self, db_path: Path, cache_events: int = 8):
        db_path.parent.mkdir(parents=True, exist_ok=True)
        self.db_path = db_path
        self.cache_events = cache_events
        self._write_lock = threading.Lock()
        self._cache: OrderedDict[str, _EventMatrix] = OrderedDict()
        self._cache_lock = threading.Lock()
        with self._connect() as conn:
            conn.execute("PRAGMA journal_mode=WAL")
            conn.executescript(SCHEMA)

    def _connect(self) -> sqlite3.Connection:
        conn = sqlite3.connect(self.db_path, timeout=30)
        conn.execute("PRAGMA foreign_keys=ON")
        return conn

    # ----- écriture -------------------------------------------------------

    def replace_photo(self, event_id: str, photo_id: str, faces: list[DetectedFace]) -> list[StoredFace]:
        """Remplace les visages d'une photo (ré-indexer une photo est idempotent)."""
        now = datetime.now(UTC).isoformat()
        stored = [
            StoredFace(uuid.uuid4().hex, photo_id, f.x, f.y, f.w, f.h, f.det_score, f.sharpness)
            for f in faces
        ]
        with self._write_lock, self._connect() as conn:
            conn.execute("DELETE FROM faces WHERE event_id = ? AND photo_id = ?", (event_id, photo_id))
            conn.executemany(
                "INSERT INTO faces VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    (s.id, event_id, photo_id, s.x, s.y, s.w, s.h, s.det_score, s.sharpness,
                     f.embedding.astype(np.float32).tobytes(), now)
                    for s, f in zip(stored, faces, strict=True)
                ],
            )
            self._bump(conn, event_id)
        return stored

    def delete_photo(self, event_id: str, photo_id: str) -> int:
        with self._write_lock, self._connect() as conn:
            deleted = conn.execute(
                "DELETE FROM faces WHERE event_id = ? AND photo_id = ?", (event_id, photo_id)
            ).rowcount
            self._bump(conn, event_id)
        return deleted

    def delete_event(self, event_id: str) -> int:
        with self._write_lock, self._connect() as conn:
            deleted = conn.execute("DELETE FROM faces WHERE event_id = ?", (event_id,)).rowcount
            conn.execute("DELETE FROM event_versions WHERE event_id = ?", (event_id,))
        with self._cache_lock:
            self._cache.pop(event_id, None)
        return deleted

    @staticmethod
    def _bump(conn: sqlite3.Connection, event_id: str) -> None:
        conn.execute(
            "INSERT INTO event_versions(event_id, version) VALUES (?, 1) "
            "ON CONFLICT(event_id) DO UPDATE SET version = version + 1",
            (event_id,),
        )

    # ----- lecture --------------------------------------------------------

    def count(self, event_id: str) -> tuple[int, int]:
        """Renvoie (nombre de visages, nombre de photos avec au moins un visage)."""
        with self._connect() as conn:
            row = conn.execute(
                "SELECT COUNT(*), COUNT(DISTINCT photo_id) FROM faces WHERE event_id = ?", (event_id,)
            ).fetchone()
        return int(row[0]), int(row[1])

    def photo_faces(self, event_id: str, photo_id: str) -> list[StoredFace]:
        with self._connect() as conn:
            rows = conn.execute(
                "SELECT id, photo_id, x, y, w, h, det_score, sharpness FROM faces "
                "WHERE event_id = ? AND photo_id = ? ORDER BY x",
                (event_id, photo_id),
            ).fetchall()
        return [StoredFace(*row) for row in rows]

    def embedding(self, event_id: str, face_id: str) -> np.ndarray | None:
        with self._connect() as conn:
            row = conn.execute(
                "SELECT embedding FROM faces WHERE event_id = ? AND id = ?", (event_id, face_id)
            ).fetchone()
        return None if row is None else np.frombuffer(row[0], dtype=np.float32)

    def _version(self, conn: sqlite3.Connection, event_id: str) -> int:
        row = conn.execute("SELECT version FROM event_versions WHERE event_id = ?", (event_id,)).fetchone()
        return 0 if row is None else int(row[0])

    def _matrix(self, event_id: str) -> _EventMatrix:
        with self._connect() as conn:
            version = self._version(conn, event_id)
            with self._cache_lock:
                cached = self._cache.get(event_id)
                if cached is not None and cached.version == version:
                    self._cache.move_to_end(event_id)
                    return cached
            rows = conn.execute(
                "SELECT id, photo_id, embedding FROM faces WHERE event_id = ?", (event_id,)
            ).fetchall()
        if rows:
            face_ids = np.array([r[0] for r in rows])
            photo_ids = np.array([r[1] for r in rows])
            vectors = np.frombuffer(b"".join(r[2] for r in rows), dtype=np.float32).reshape(-1, EMBEDDING_DIM)
        else:
            face_ids = photo_ids = np.array([], dtype=str)
            vectors = np.zeros((0, EMBEDDING_DIM), dtype=np.float32)
        matrix = _EventMatrix(version, face_ids, photo_ids, vectors)
        with self._cache_lock:
            self._cache[event_id] = matrix
            self._cache.move_to_end(event_id)
            while len(self._cache) > self.cache_events:
                self._cache.popitem(last=False)
        return matrix

    def search(self, event_id: str, query: np.ndarray, threshold: float, limit: int) -> list[Match]:
        """Photos de l'événement contenant un visage proche de ``query`` (meilleur visage par photo)."""
        matrix = self._matrix(event_id)
        if matrix.vectors.shape[0] == 0:
            return []
        sims = matrix.vectors @ np.asarray(query, dtype=np.float32)
        keep = np.nonzero(sims >= threshold)[0]
        keep = keep[np.argsort(-sims[keep], kind="stable")]
        best: dict[str, Match] = {}
        for i in keep:
            photo_id = str(matrix.photo_ids[i])
            if photo_id not in best:
                best[photo_id] = Match(photo_id, str(matrix.face_ids[i]), round(float(sims[i]), 4))
                if len(best) >= limit:
                    break
        return list(best.values())
