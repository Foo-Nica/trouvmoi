"""Tests du vrai moteur OpenCV (sautés si les modèles ne sont pas téléchargés).

Pour tester la reconnaissance sur de vraies photos, placez des images dans
``tests/fixtures/faces/`` (non versionné) : chaque image doit contenir au moins un visage.
"""

import io
from pathlib import Path

import numpy as np
import pytest
from PIL import Image

from app.config import Settings
from app.engine import InvalidImage, OpenCVFaceEngine, decode_image

ROOT = Path(__file__).resolve().parents[1]
MODELS = ROOT / "models"
FIXTURES = Path(__file__).parent / "fixtures" / "faces"

needs_models = pytest.mark.skipif(
    not (MODELS / "face_recognition_sface_2021dec.onnx").is_file(), reason="modèles absents"
)


def jpeg(width=800, height=600, color=(200, 180, 160)) -> bytes:
    buf = io.BytesIO()
    Image.new("RGB", (width, height), color).save(buf, "JPEG")
    return buf.getvalue()


@pytest.fixture(scope="module")
def engine():
    return OpenCVFaceEngine(Settings(api_token="x", models_dir=MODELS))


def test_decode_rejects_garbage():
    with pytest.raises(InvalidImage):
        decode_image(b"pas une image")


def test_decode_applies_exif_orientation():
    buf = io.BytesIO()
    img = Image.new("RGB", (400, 200))
    exif = img.getexif()
    exif[0x0112] = 6  # rotation de 90°
    img.save(buf, "JPEG", exif=exif)
    assert decode_image(buf.getvalue()).shape[:2] == (400, 200)


def test_missing_models_message(tmp_path):
    with pytest.raises(FileNotFoundError):
        OpenCVFaceEngine(Settings(api_token="x", models_dir=tmp_path))


@needs_models
def test_blank_image_has_no_face(engine):
    assert engine.analyze(jpeg()) == []


@needs_models
def test_large_image_is_downscaled_without_error(engine):
    assert engine.analyze(jpeg(6000, 4000)) == []


@needs_models
@pytest.mark.skipif(not FIXTURES.is_dir(), reason="aucune photo de test dans tests/fixtures/faces")
def test_real_photos_produce_normalized_embeddings(engine):
    files = sorted(p for p in FIXTURES.iterdir() if p.suffix.lower() in {".jpg", ".jpeg", ".png", ".heic"})
    for path in files:
        faces = engine.analyze(path.read_bytes())
        assert faces, f"aucun visage détecté dans {path.name}"
        for face in faces:
            assert face.embedding.shape == (128,)
            assert abs(float(np.linalg.norm(face.embedding)) - 1.0) < 1e-4
