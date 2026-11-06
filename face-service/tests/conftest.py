"""Outils de test : un faux moteur déterministe pour tester l'API sans modèles ni photos.

Une « image » de test est un texte du type ``b"alice,bob"`` : chaque nom devient un
visage dont l'empreinte est propre à la personne (avec un léger bruit par photo).
"""

import hashlib
import sys
from pathlib import Path

import numpy as np
import pytest
from fastapi.testclient import TestClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.config import Settings  # noqa: E402
from app.engine import EMBEDDING_DIM, DetectedFace, InvalidImage, normalize  # noqa: E402
from app.main import create_app  # noqa: E402
from app.store import FaceStore  # noqa: E402

TOKEN = "test-token"


def person_vector(name: str) -> np.ndarray:
    seed = int.from_bytes(hashlib.sha256(name.encode()).digest()[:4], "big")
    return normalize(np.random.default_rng(seed).standard_normal(EMBEDDING_DIM))


class FakeEngine:
    def analyze(self, data: bytes) -> list[DetectedFace]:
        text = data.decode(errors="replace")
        if text.startswith("not-an-image"):
            raise InvalidImage("Image illisible ou format non pris en charge")
        faces = []
        for i, name in enumerate(n for n in text.split(",") if n):
            noise = np.random.default_rng(hash((text, i)) & 0xFFFF).standard_normal(EMBEDDING_DIM) * 0.02
            vec = normalize(person_vector(name) + noise)
            faces.append(DetectedFace(x=100 * i, y=10, w=80 + i, h=90, det_score=0.95, sharpness=80.0,
                                      embedding=vec))
        return faces


@pytest.fixture
def settings(tmp_path):
    return Settings(api_token=TOKEN, data_dir=tmp_path, models_dir=tmp_path)


@pytest.fixture
def store(settings):
    return FaceStore(settings.db_path, cache_events=2)


@pytest.fixture
def client(settings, store):
    app = create_app(settings=settings, engine=FakeEngine(), store=store)
    with TestClient(app, headers={"Authorization": f"Bearer {TOKEN}"}) as c:
        yield c


def upload(client, event, photo, people: str):
    return client.post(f"/v1/events/{event}/photos/{photo}/index",
                       files={"image": ("p.jpg", people.encode(), "image/jpeg")})
