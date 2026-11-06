# TrouvMoi

Plateforme de photos d'événements : les photographes publient leurs photos traitées, chaque
invité scanne un code QR, montre son visage une fois et retrouve toutes les photos où il
apparaît grâce à la reconnaissance faciale, dans un dossier à télécharger.

Le cahier des charges complet est la référence fonctionnelle de ce dépôt.

## Architecture

| Partie | Technologie | Dossier |
| --- | --- | --- |
| Application web, espace photographe, back-office | Laravel 13, Filament 5, Livewire, Tailwind 4 | racine |
| Files d'attente (traitement, indexation) | Redis + Laravel Horizon | `app/Jobs` |
| Stockage des photos | Disque local (dev), Cloudflare R2 ou S3 (prod) | `config/filesystems.php` |
| Reconnaissance faciale **auto-hébergée** | Python, FastAPI, OpenCV (YuNet MIT + SFace Apache 2.0) | `face-service/` |
| Base de données | MySQL 8 (SQLite pour les tests) | `database/migrations` |

Parcours d'une photo : envoi depuis le navigateur (Uppy, 4 en parallèle, reprise automatique)
→ `ProcessPhoto` (miniature 400 px, aperçu 1600 px WebP, version téléchargeable JPEG, filigrane,
métadonnées EXIF retirées) → `IndexPhotoFaces` (le service IA détecte les visages et garde leurs
empreintes ; Laravel garde seulement les cadres).

## État d'avancement

| Lot | Contenu | État |
| --- | --- | --- |
| 1. Socle | Laravel, Filament, files, stockage, CI | ✅ |
| 2. Événements et envoi | Événements, QR code, envoi reprenable, miniatures, filigrane | ✅ |
| 3. IA | Service facial auto-hébergé, indexation, recherche galerie + selfie | ✅ |
| 4. Invité | Consentement, PIN, galerie, toucher son visage, selfie, dossier, ZIP, WhatsApp, retrait, FR/EN/ES/DE | ✅ |
| 5. Pilote | Calibration des seuils, tests de charge, pages légales | ⏳ |

## Installation (développement)

Prérequis : PHP 8.3+ (extensions gd, exif, intl, zip, pcntl), Composer, Node 22, Python 3.11+,
Redis, MySQL 8.

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
# Renseigner DB_*, puis :
php artisan migrate --seed          # comptes démo : admin@trouvmoi.test / photo@trouvmoi.test (mot de passe : password)
php artisan storage:link

# Service de reconnaissance faciale
cd face-service
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements-dev.txt
sh scripts/download_models.sh
echo "FACE_API_TOKEN=un-long-jeton" > .env          # même valeur que FACE_ENGINE_TOKEN côté Laravel
uvicorn app.main:app_factory --factory --port 8001
```

Dans un autre terminal : `php artisan serve` et `php artisan horizon` (ou `php artisan queue:work --queue=photos,faces,default`).

- Espace photographe : `/studio`
- Back-office (administrateurs) : `/admin` · tableau des files : `/horizon`
- Page invités d'un événement : `/e/{code}` (adresse contenue dans le QR code)
- Dossier personnel d'un invité : `/d/{lien privé}`
- Traductions des pages invités : `lang/en.json`, `lang/es.json`, `lang/de.json` (le français est la langue source)

## Tests

```bash
php artisan test                    # Laravel
cd face-service && pytest           # service IA
```

## Production

- `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, Horizon supervisé (Laravel Forge ou Ploi).
- `PHOTOS_DISK=r2` avec les variables `R2_*` (pas de frais de sortie sur les téléchargements).
- Photos HEIC : installer l'extension PHP Imagick compilée avec libheif.
- Le service IA tourne sur le même serveur ou un serveur voisin, jamais exposé publiquement
  (`docker build -t trouvmoi-face face-service`).
- Planificateur : `* * * * * php artisan schedule:run` (suppression automatique des données
  faciales à l'expiration des événements).
- Avant le lancement : calibrer les seuils avec `face-service/scripts/calibrate.py` sur de
  vraies photos, et obtenir l'autorisation de l'APDP.
