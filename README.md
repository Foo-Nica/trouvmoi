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
| 5. Finitions | Pages légales, en-têtes de sécurité, durée de conservation, statistiques, alertes de retrait | ✅ |
| Reste | Calibration sur de vraies photos, paiement mobile money | ⏳ |

## Installation locale (VS Code)

Prérequis : **PHP 8.3+** avec les extensions `gd`, `exif`, `intl`, `zip`, `fileinfo`, `pdo_sqlite`
(Laragon, Herd ou XAMPP sous Windows), **Composer**, **Node 22**, et pour la reconnaissance faciale
**Python 3.11+** ou **Docker Desktop**.

### 1. Application Laravel

L'archive livrée contient déjà `vendor/` et les fichiers compilés (`public/build`) : les étapes
`composer install` et `npm run build` ne sont nécessaires qu'après un `git clone`.

```bash
composer install                     # seulement après un git clone
npm install && npm run build         # seulement après un git clone
cp .env.example .env                 # Windows : copy .env.example .env
php artisan key:generate
php artisan migrate --seed           # base SQLite + comptes de démonstration
php artisan storage:link
composer run dev                     # serveur + file de traitement + Vite
```

Ouvrez <http://127.0.0.1:8000>. Comptes de démonstration (mot de passe : `password`) :

| Compte | Accès |
| --- | --- |
| `photo@trouvmoi.test` | Espace photographe `/studio` |
| `admin@trouvmoi.test` | Back-office `/admin` (et `/horizon` en production) |

Les e-mails (vérification d'adresse, demandes de retrait) sont écrits dans `storage/logs/laravel.log`
tant que `MAIL_MAILER=log`. Avec Docker, `MAIL_MAILER=smtp` et `MAIL_PORT=1025` les affichent dans
Mailpit (<http://localhost:8025>).

### 2. Service de reconnaissance faciale

Choisissez un jeton secret et mettez la **même valeur** dans `FACE_ENGINE_TOKEN` (`.env` de Laravel)
et `FACE_API_TOKEN` (`face-service/.env`).

Avec Docker :

```bash
docker compose up -d face            # lit FACE_ENGINE_TOKEN dans .env
```

Ou avec Python :

```bash
cd face-service
python -m venv .venv
.venv\Scripts\activate               # macOS / Linux : source .venv/bin/activate
pip install -r requirements-dev.txt
sh scripts/download_models.sh        # inutile si models/ contient déjà les 2 fichiers .onnx
echo FACE_API_TOKEN=votre-jeton > .env
uvicorn app.main:app_factory --factory --port 8001
```

Sans ce service, l'envoi et l'affichage des photos fonctionnent, mais les photos passent en
« Erreur » à l'étape de reconnaissance faciale (bouton « Relancer » une fois le service démarré).

### Adresses utiles

- Espace photographe : `/studio` · back-office : `/admin`
- Page invités d'un événement : `/e/{code}` (adresse contenue dans le QR code)
- Dossier personnel d'un invité : `/d/{lien privé}`
- Pages légales : `/confidentialite` et `/conditions` (mentions dans `.env` : `LEGAL_*`)
- Traductions des pages invités : `lang/en.json`, `lang/es.json`, `lang/de.json` (le français est la langue source)

## Tests

```bash
php artisan test                    # Laravel
cd face-service && pytest           # service IA
```

## Production

### Railway

Le dépôt contient la configuration Railway : `railway/web.json` (site), `railway/worker.json`
(Horizon), `railway/scheduler.json` (tâches planifiées) et `face-service/railway.json` (IA).
Chaque service Railway pointe vers son fichier (Settings → Config-as-code). Le site exécute
`php artisan app:deploy` avant chaque mise en ligne : migrations, puis compte administrateur
défini par `ADMIN_EMAIL` et `ADMIN_PASSWORD`. Les réglages PHP de production sont dans `php.ini`.

### Généralités

- `DB_CONNECTION=mysql`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, Horizon supervisé
  (Laravel Forge ou Ploi, serveur Linux).
- `PHOTOS_DISK=r2` avec les variables `R2_*` (pas de frais de sortie sur les téléchargements).
- Photos HEIC : installer l'extension PHP Imagick compilée avec libheif.
- Le service IA tourne sur le même serveur ou un serveur voisin, jamais exposé publiquement
  (`docker build -t trouvmoi-face face-service`).
- Planificateur : `* * * * * php artisan schedule:run`. La commande `events:purge-expired`
  supprime chaque nuit les données faciales des événements expirés, puis leurs photos
  `PHOTOS_GRACE_DAYS` jours plus tard.
- Avant le lancement : calibrer les seuils avec `face-service/scripts/calibrate.py` sur de
  vraies photos, et renseigner `LEGAL_APDP_REFERENCE`.
