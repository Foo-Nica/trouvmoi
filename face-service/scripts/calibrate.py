"""Calibre les seuils de similarité sur vos propres photos d'événement.

Préparation (répondre au critère de recette : 20 personnes étiquetées) :

    calibration/
      photos/            photos de l'événement (JPEG, PNG, HEIC)
      queries/           un selfie par personne : alice.jpg, bob.jpg, ...
      labels.csv         une ligne par (photo, personne) : photo,personne
                         ex. IMG_0012.jpg,alice

Lancement :

    python scripts/calibrate.py calibration

Pour chaque seuil, le script affiche le rappel (part des vraies photos retrouvées)
et le taux de fausses photos. Choisissez le plus petit seuil qui garde moins de 3 %
de fausses photos, puis reportez-le dans FACE_MATCH_THRESHOLD.
"""

from __future__ import annotations

import csv
import sys
import tempfile
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.config import Settings  # noqa: E402
from app.engine import OpenCVFaceEngine  # noqa: E402
from app.store import FaceStore  # noqa: E402

IMAGE_EXT = {".jpg", ".jpeg", ".png", ".heic", ".webp"}
THRESHOLDS = [0.30, 0.33, 0.363, 0.40, 0.45, 0.50, 0.55]


def main(root: Path) -> None:
    settings = Settings(api_token="calibration")
    engine = OpenCVFaceEngine(settings)
    store = FaceStore(Path(tempfile.mkdtemp()) / "calib.sqlite3")

    truth: dict[str, set[str]] = defaultdict(set)
    with open(root / "labels.csv", newline="", encoding="utf-8") as fh:
        for row in csv.reader(fh):
            if len(row) >= 2 and not row[0].startswith("#"):
                truth[row[1].strip()].add(Path(row[0].strip()).stem)

    photos = sorted(p for p in (root / "photos").iterdir() if p.suffix.lower() in IMAGE_EXT)
    print(f"Indexation de {len(photos)} photos...")
    total_faces = 0
    for path in photos:
        faces = engine.analyze(path.read_bytes())
        store.replace_photo("calib", path.stem, faces)
        total_faces += len(faces)
    print(f"{total_faces} visages indexés.\n")

    queries = {}
    for path in sorted((root / "queries").iterdir()):
        if path.suffix.lower() not in IMAGE_EXT:
            continue
        faces = engine.analyze(path.read_bytes())
        if not faces:
            print(f"! aucun visage sur le selfie {path.name}")
            continue
        queries[path.stem] = max(faces, key=lambda f: f.area).embedding

    print(f"{'seuil':>7} {'rappel':>8} {'fausses':>8}")
    for threshold in THRESHOLDS:
        found = relevant = false_hits = returned = 0
        for person, vector in queries.items():
            hits = {m.photo_id for m in store.search("calib", vector, threshold, 100000)}
            expected = truth.get(person, set())
            found += len(hits & expected)
            relevant += len(expected)
            false_hits += len(hits - expected)
            returned += len(hits)
        recall = found / relevant if relevant else 0.0
        false_rate = false_hits / returned if returned else 0.0
        print(f"{threshold:>7.3f} {recall:>7.1%} {false_rate:>7.1%}")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(Path(sys.argv[1]))
