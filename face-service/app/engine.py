"""Détection et encodage des visages.

Modèles (licences compatibles avec un usage commercial) :
- YuNet (OpenCV Zoo, licence MIT) pour la détection ;
- SFace (OpenCV Zoo, licence Apache 2.0) pour l'empreinte de 128 dimensions.
"""

from __future__ import annotations

import io
import threading
from dataclasses import dataclass
from typing import Protocol

import cv2
import numpy as np
from PIL import Image, ImageOps, UnidentifiedImageError

try:  # Prise en charge des photos HEIC (iPhone).
    import pillow_heif

    pillow_heif.register_heif_opener()
except ImportError:  # pragma: no cover
    pass

from .config import Settings

EMBEDDING_DIM = 128


class InvalidImage(ValueError):
    """Le fichier reçu n'est pas une image lisible."""


@dataclass(frozen=True)
class DetectedFace:
    x: int
    y: int
    w: int
    h: int
    det_score: float
    sharpness: float
    embedding: np.ndarray  # float32, normalisé (norme 1)

    @property
    def area(self) -> int:
        return self.w * self.h


class Engine(Protocol):
    def analyze(self, data: bytes) -> list[DetectedFace]: ...


def decode_image(data: bytes) -> np.ndarray:
    """Décode une image (JPEG, PNG, WebP, HEIC), applique l'orientation EXIF, renvoie du BGR."""
    try:
        with Image.open(io.BytesIO(data)) as img:
            img = ImageOps.exif_transpose(img)
            rgb = np.asarray(img.convert("RGB"))
    except (UnidentifiedImageError, OSError, ValueError) as exc:
        raise InvalidImage("Image illisible ou format non pris en charge") from exc
    return cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)


def normalize(vec: np.ndarray) -> np.ndarray:
    vec = np.asarray(vec, dtype=np.float32).reshape(-1)
    norm = float(np.linalg.norm(vec))
    return vec / norm if norm > 0 else vec


class OpenCVFaceEngine:
    """Moteur YuNet + SFace. Les modèles OpenCV ne sont pas partagés entre threads."""

    def __init__(self, settings: Settings):
        for path in (settings.detector_path, settings.recognizer_path):
            if not path.is_file():
                raise FileNotFoundError(
                    f"Modèle introuvable : {path}. Lancez scripts/download_models.sh"
                )
        self.settings = settings
        self._local = threading.local()

    def _models(self) -> tuple[cv2.FaceDetectorYN, cv2.FaceRecognizerSF]:
        if not hasattr(self._local, "detector"):
            s = self.settings
            self._local.detector = cv2.FaceDetectorYN.create(
                str(s.detector_path), "", (320, 320), s.detect_score_threshold, 0.3, 5000
            )
            self._local.recognizer = cv2.FaceRecognizerSF.create(str(s.recognizer_path), "")
        return self._local.detector, self._local.recognizer

    def analyze(self, data: bytes) -> list[DetectedFace]:
        return self.analyze_image(decode_image(data))

    def analyze_image(self, image: np.ndarray) -> list[DetectedFace]:
        detector, recognizer = self._models()
        s = self.settings
        height, width = image.shape[:2]
        scale = min(1.0, s.detect_max_side / max(height, width))
        small = (
            cv2.resize(image, (round(width * scale), round(height * scale)), interpolation=cv2.INTER_AREA)
            if scale < 1.0
            else image
        )
        detector.setInputSize((small.shape[1], small.shape[0]))
        _, rows = detector.detect(small)
        if rows is None:
            return []

        faces: list[DetectedFace] = []
        for row in rows:
            original = row.copy()
            original[:14] /= scale  # cadre + 5 points repères, ramenés à l'image d'origine
            x, y, w, h = (float(v) for v in original[:4])
            if min(w, h) < s.min_face_size:
                continue
            aligned = recognizer.alignCrop(image, original)
            gray = cv2.cvtColor(aligned, cv2.COLOR_BGR2GRAY)
            sharpness = float(cv2.Laplacian(gray, cv2.CV_64F).var())
            if sharpness < s.min_sharpness:
                continue
            embedding = normalize(recognizer.feature(aligned))
            x0, y0 = max(0, round(x)), max(0, round(y))
            faces.append(
                DetectedFace(
                    x=x0,
                    y=y0,
                    w=min(round(w), width - x0),
                    h=min(round(h), height - y0),
                    det_score=round(float(row[14]), 4),
                    sharpness=round(sharpness, 2),
                    embedding=embedding,
                )
            )
        return faces
