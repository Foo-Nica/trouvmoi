"""API HTTP du service de reconnaissance faciale TrouvMoi.

Appelée uniquement par l'application Laravel (jeton partagé), jamais par le navigateur.
Les selfies envoyés pour une recherche sont traités en mémoire et jamais enregistrés.
"""

from __future__ import annotations

import hmac
import logging
from typing import Annotated

import numpy as np
from fastapi import Depends, FastAPI, File, HTTPException, Path, Request, UploadFile, status
from pydantic import BaseModel, Field

from .config import Settings, get_settings
from .engine import Engine, InvalidImage, OpenCVFaceEngine, normalize
from .store import FaceStore

log = logging.getLogger("trouvmoi.face")

ID_PATTERN = r"^[A-Za-z0-9_-]{1,64}$"
IdParam = Annotated[str, Path(pattern=ID_PATTERN)]


# ----- schémas --------------------------------------------------------------

class FaceOut(BaseModel):
    face_id: str
    x: int
    y: int
    w: int
    h: int
    det_score: float
    sharpness: float


class IndexOut(BaseModel):
    event_id: str
    photo_id: str
    faces: list[FaceOut]


class MatchOut(BaseModel):
    photo_id: str
    face_id: str
    similarity: float
    tier: str = Field(description="« strong » : vos photos ; « maybe » : peut-être vous")


class SearchOut(BaseModel):
    event_id: str
    matches: list[MatchOut]
    query_face: FaceOut | None = None


class FaceSearchIn(BaseModel):
    face_ids: list[Annotated[str, Field(pattern=ID_PATTERN)]] = Field(min_length=1, max_length=20)
    limit: int = Field(default=2000, ge=1, le=20000)


class DeletedOut(BaseModel):
    deleted_faces: int


class StatsOut(BaseModel):
    event_id: str
    faces: int
    photos_with_faces: int


# ----- application ----------------------------------------------------------

def create_app(
    settings: Settings | None = None,
    engine: Engine | None = None,
    store: FaceStore | None = None,
) -> FastAPI:
    settings = settings or get_settings()
    if not settings.api_token:
        raise RuntimeError("FACE_API_TOKEN doit être défini (jeton partagé avec Laravel).")

    engine = engine or OpenCVFaceEngine(settings)
    store = store or FaceStore(settings.db_path, settings.cache_events)
    max_bytes = settings.max_upload_mb * 1024 * 1024

    app = FastAPI(title="TrouvMoi Face", version="1.0.0")

    def authorize(request: Request) -> None:
        header = request.headers.get("authorization", "")
        token = header.removeprefix("Bearer ").strip() if header.startswith("Bearer ") else ""
        if not token or not hmac.compare_digest(token, settings.api_token):
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Jeton invalide")

    Auth = Depends(authorize)

    async def read_upload(image: UploadFile) -> bytes:
        data = await image.read(max_bytes + 1)
        if len(data) > max_bytes:
            raise HTTPException(status.HTTP_413_CONTENT_TOO_LARGE, "Image trop lourde")
        if not data:
            raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "Fichier vide")
        return data

    def analyze(data: bytes):
        try:
            return engine.analyze(data)
        except InvalidImage as exc:
            raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(exc)) from exc

    def tier(similarity: float) -> str:
        return "strong" if similarity >= settings.strong_threshold else "maybe"

    def to_matches(matches) -> list[MatchOut]:
        return [MatchOut(photo_id=m.photo_id, face_id=m.face_id, similarity=m.similarity,
                         tier=tier(m.similarity)) for m in matches]

    @app.get("/health")
    def health() -> dict:
        return {"status": "ok"}

    @app.post("/v1/events/{event_id}/photos/{photo_id}/index", response_model=IndexOut, dependencies=[Auth])
    async def index_photo(event_id: IdParam, photo_id: IdParam, image: UploadFile = File(...)):
        data = await read_upload(image)
        faces = await run_sync(analyze, data)
        stored = store.replace_photo(event_id, photo_id, faces)
        return IndexOut(event_id=event_id, photo_id=photo_id,
                        faces=[FaceOut(face_id=s.id, **_box(s)) for s in stored])

    @app.get("/v1/events/{event_id}/photos/{photo_id}/faces", response_model=IndexOut, dependencies=[Auth])
    def photo_faces(event_id: IdParam, photo_id: IdParam):
        faces = store.photo_faces(event_id, photo_id)
        return IndexOut(event_id=event_id, photo_id=photo_id,
                        faces=[FaceOut(face_id=s.id, **_box(s)) for s in faces])

    @app.delete("/v1/events/{event_id}/photos/{photo_id}", response_model=DeletedOut, dependencies=[Auth])
    def delete_photo(event_id: IdParam, photo_id: IdParam):
        return DeletedOut(deleted_faces=store.delete_photo(event_id, photo_id))

    @app.delete("/v1/events/{event_id}", response_model=DeletedOut, dependencies=[Auth])
    def delete_event(event_id: IdParam):
        return DeletedOut(deleted_faces=store.delete_event(event_id))

    @app.get("/v1/events/{event_id}/stats", response_model=StatsOut, dependencies=[Auth])
    def stats(event_id: IdParam):
        faces, photos = store.count(event_id)
        return StatsOut(event_id=event_id, faces=faces, photos_with_faces=photos)

    @app.post("/v1/events/{event_id}/search/faces", response_model=SearchOut, dependencies=[Auth])
    def search_by_faces(event_id: IdParam, body: FaceSearchIn):
        """Mode galerie : l'invité a touché son visage (ou confirmé plusieurs photos)."""
        vectors = []
        for face_id in body.face_ids:
            vec = store.embedding(event_id, face_id)
            if vec is None:
                raise HTTPException(status.HTTP_404_NOT_FOUND, f"Visage inconnu : {face_id}")
            vectors.append(vec)
        query = normalize(np.mean(vectors, axis=0))
        matches = store.search(event_id, query, settings.match_threshold, body.limit)
        return SearchOut(event_id=event_id, matches=to_matches(matches))

    @app.post("/v1/events/{event_id}/search/selfie", response_model=SearchOut, dependencies=[Auth])
    async def search_by_selfie(event_id: IdParam, image: UploadFile = File(...), limit: int = 2000):
        """Mode selfie : le selfie est analysé en mémoire puis oublié."""
        data = await read_upload(image)
        faces = await run_sync(analyze, data)
        del data
        if not faces:
            raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "Aucun visage détecté sur le selfie")
        face = max(faces, key=lambda f: f.area)
        matches = store.search(event_id, face.embedding, settings.match_threshold, max(1, min(limit, 20000)))
        query = FaceOut(face_id="selfie", x=face.x, y=face.y, w=face.w, h=face.h,
                        det_score=face.det_score, sharpness=face.sharpness)
        return SearchOut(event_id=event_id, matches=to_matches(matches), query_face=query)

    return app


def _box(face) -> dict:
    return {"x": face.x, "y": face.y, "w": face.w, "h": face.h,
            "det_score": face.det_score, "sharpness": face.sharpness}


async def run_sync(fn, *args):
    from starlette.concurrency import run_in_threadpool

    return await run_in_threadpool(fn, *args)


def app_factory() -> FastAPI:  # point d'entrée uvicorn : app.main:app_factory --factory
    return create_app()


__all__ = ["create_app", "app_factory", "ID_PATTERN"]
