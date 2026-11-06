# TrouvMoi Face — service de reconnaissance faciale auto-hébergé

Service interne appelé par l'application Laravel TrouvMoi. Il détecte les visages de chaque
photo d'un événement, en calcule l'empreinte et retrouve toutes les photos d'une personne à
partir d'un visage touché dans la galerie ou d'un selfie.

- **Détection** : YuNet (OpenCV Zoo, licence MIT)
- **Empreinte** : SFace, 128 dimensions (OpenCV Zoo, licence Apache 2.0)
- **Stockage** : SQLite, une collection isolée par événement ; recherche en mémoire (produit matriciel)
- **Aucun GPU requis**, aucune donnée envoyée à un tiers. Les selfies ne sont jamais enregistrés.

## Démarrage

```bash
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements-dev.txt
sh scripts/download_models.sh            # ~39 Mo, empreintes SHA-256 vérifiées
cp .env.example .env                     # puis renseigner FACE_API_TOKEN
uvicorn app.main:app_factory --factory --port 8001
pytest
```

Avec Docker : `docker build -t trouvmoi-face . && docker run -p 8001:8001 -v face-data:/data -e FACE_API_TOKEN=... trouvmoi-face`

## API

Toutes les routes sauf `/health` exigent l'en-tête `Authorization: Bearer <FACE_API_TOKEN>`.
Les identifiants d'événement et de photo sont ceux de Laravel (`[A-Za-z0-9_-]`, 64 caractères max).

| Méthode | Route | Rôle |
| --- | --- | --- |
| GET | `/health` | Contrôle de vie |
| POST | `/v1/events/{event}/photos/{photo}/index` | Indexe une photo (champ multipart `image`). Ré-indexer remplace les visages |
| GET | `/v1/events/{event}/photos/{photo}/faces` | Visages d'une photo avec leur cadre (pour que l'invité touche le sien) |
| DELETE | `/v1/events/{event}/photos/{photo}` | Retire une photo et ses visages |
| DELETE | `/v1/events/{event}` | Supprime toute la collection (expiration de l'événement) |
| GET | `/v1/events/{event}/stats` | Nombre de visages et de photos indexées |
| POST | `/v1/events/{event}/search/faces` | Mode galerie : `{"face_ids": ["..."], "limit": 2000}` |
| POST | `/v1/events/{event}/search/selfie` | Mode selfie (champ multipart `image`), le plus grand visage est utilisé |

Une recherche renvoie la meilleure correspondance par photo, triée par similarité, avec
`tier` = `strong` (« Vos photos ») ou `maybe` (« Peut-être vous »).
Plusieurs `face_ids` (photos déjà confirmées par l'invité) affinent la recherche.

## Réglages et calibration

| Variable | Défaut | Effet |
| --- | --- | --- |
| `FACE_MATCH_THRESHOLD` | 0.363 | Similarité minimale (seuil publié par OpenCV pour SFace) |
| `FACE_STRONG_THRESHOLD` | 0.45 | Au-dessus : « Vos photos » |
| `FACE_MIN_FACE_SIZE` | 60 | Visages plus petits ignorés (pixels) |
| `FACE_MIN_SHARPNESS` | 20 | Visages trop flous ignorés (0 = désactivé) |

Ces valeurs sont des points de départ. Avant le lancement, calibrez-les sur de vraies
photos d'événement (peaux foncées, éclairage de soirée, flash) avec
`python scripts/calibrate.py calibration` — le mode d'emploi est en tête du script.
