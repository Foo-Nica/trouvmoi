"""Configuration du service de reconnaissance faciale TrouvMoi.

Toutes les valeurs se règlent par variables d'environnement préfixées ``FACE_``
(ou un fichier ``.env``). Les seuils de similarité sont des valeurs de départ :
ils doivent être calibrés sur un jeu de photos d'événements réels.
"""

from functools import lru_cache
from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="FACE_", env_file=".env", extra="ignore")

    # Jeton partagé avec l'application Laravel (en-tête Authorization: Bearer ...).
    api_token: str = ""

    data_dir: Path = Path("data")
    models_dir: Path = Path("models")
    detector_model: str = "face_detection_yunet_2023mar.onnx"
    recognizer_model: str = "face_recognition_sface_2021dec.onnx"

    # Détection : l'image est réduite à ce côté maximal avant détection (vitesse).
    detect_max_side: int = 1920
    detect_score_threshold: float = 0.8
    # Visages ignorés en dessous de cette taille (côté le plus court, en pixels de l'original).
    min_face_size: int = 60
    # Netteté minimale (variance du laplacien sur le visage aligné 112x112). 0 = désactivé
    # (par défaut, tant que le seuil n'est pas calibré sur de vraies photos).
    min_sharpness: float = 0.0

    # Similarité cosinus SFace : 0.363 est le seuil « même personne » publié par OpenCV.
    match_threshold: float = 0.363
    # Au-dessus de ce seuil : « Vos photos » ; entre les deux : « Peut-être vous ».
    strong_threshold: float = 0.45

    max_upload_mb: int = 30
    # Nombre d'événements dont les vecteurs restent en mémoire pour la recherche.
    cache_events: int = 8

    @property
    def detector_path(self) -> Path:
        return self.models_dir / self.detector_model

    @property
    def recognizer_path(self) -> Path:
        return self.models_dir / self.recognizer_model

    @property
    def db_path(self) -> Path:
        return self.data_dir / "faces.sqlite3"


@lru_cache
def get_settings() -> Settings:
    return Settings()
