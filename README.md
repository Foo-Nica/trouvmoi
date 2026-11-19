# TRECHART EMPIRE - livraison des photos d'événements

Site de TRECHART EMPIRE, studio créatif et de production visuelle : l'équipe publie les photos
traitées de chaque événement couvert, chaque invité scanne un code QR (ou saisit le code de
l'événement sur la page d'accueil), montre son visage une fois et retrouve toutes les photos où
il apparaît grâce à la reconnaissance faciale, dans un dossier à télécharger. Les photos portent
le logo TRECHART en filigrane.

Le site est réservé à la marque : pas d'inscription publique, les comptes de l'équipe sont créés
par l'administrateur dans `/admin` (menu « Équipe »).

La charte graphique (logo, couleurs #333333 et #ED8B00, police Satoshi) est appliquée au site,
aux espaces `/studio` et `/admin`, au code QR et au filigrane. Les fichiers du logo sont dans
`public/images/`, le filigrane dans `resources/images/watermark.png`.

## Architecture

| Partie | Technologie | Dossier |
| --- | --- | --- |
| Application web, espace équipe, back-office | Laravel 13, Filament 5, Livewire, Tailwind 4 | racine |
| Files d'attente (traitement, indexation) | Redis + Laravel Horizon | `app/Jobs` |
| Stockage des photos | Disque local (dev), Cloudflare R2 ou S3 (prod) | `config/filesystems.php` |
| Reconnaissance faciale **auto-hébergée** | Python, FastAPI, OpenCV (YuNet MIT + SFace Apache 2.0) | `face-service/` |
| Base de données | MySQL 8 (SQLite pour les tests) | `database/migrations` |

Parcours d'une photo : envoi depuis le navigateur (Uppy, 4 en parallèle, reprise automatique)
→ `ProcessPhoto` (miniature 400 px, aperçu 1600 px WebP, version téléchargeable JPEG avec logo,
version originale pleine qualité avec un logo minuscule, métadonnées EXIF retirées) → `IndexPhotoFaces` (le service IA détecte les visages et garde leurs
empreintes ; Laravel garde seulement les cadres).

## État d'avancement

| Lot | Contenu | État |
| --- | --- | --- |
| 1. Socle | Laravel, Filament, files, stockage, CI | ✅ |
| 2. Événements et envoi | Événements, QR code, envoi reprenable, miniatures, filigrane | ✅ |
| 3. IA | Service facial auto-hébergé, indexation, recherche galerie + selfie | ✅ |
| 4. Invité | Consentement, PIN, galerie, toucher son visage, selfie, dossier, ZIP, WhatsApp, retrait, FR/EN/ES/DE | ✅ |
| 5. Finitions | Pages légales, en-têtes de sécurité, durée de conservation, statistiques, alertes de retrait | ✅ |
| 6. Ventes | Types d'événement (public, personnalité), prix, panier, paiement KKiaPay / FedaPay, originaux | ✅ |
| Reste | Calibration sur de vraies photos, clés de paiement réelles | ⏳ |

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
| `admin@trechart.test` | Direction : `/studio` et back-office `/admin` |
| `equipe@trechart.test` | Membre de l'équipe : `/studio` |

La base livrée contient deux événements de démonstration (« Gala TRECHART 2026 », public, et
« Shooting portrait - Mme Awa Mensah », personnalité) avec des images d'illustration et des ventes.
Pour repartir de zéro : `php artisan migrate:fresh --seed`.

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

- Espace équipe : `/studio` · back-office : `/admin`
- Accès invités par code : page d'accueil, champ « Code de l'événement » (code affiché dans `/studio`, colonne « Code invités »)
- Page invités d'un événement : `/e/{code}` (adresse contenue dans le QR code)
- Dossier personnel d'un invité : `/d/{lien privé}`
- Pages légales : `/confidentialite` et `/conditions` (mentions dans `.env` : `LEGAL_*`)
- Traductions des pages invités : `lang/en.json`, `lang/es.json`, `lang/de.json` (le français est la langue source)

## Types d'événements et ventes

| Type | Aperçus | Téléchargement gratuit | Version originale |
| --- | --- | --- | --- |
| Événement public | Logo TRECHART (réglable) | Oui, avec le logo | Payante si un prix est fixé |
| Personnalité (client privé) | Toujours avec le logo | Non | Payante (prix par photo ou forfait), offerte si le prix est vide |

- Les prix se règlent à la création de l'événement (`/studio`, section « Type de prestation et
  tarifs ») et photo par photo dans l'onglet « Photos » (colonne « Prix » ou action groupée
  « Définir le prix »).
- La version originale est générée pour chaque photo (`hd_path`) : pleine qualité, sans
  filigrane, avec un logo minuscule et discret en bas à droite.
- Les ventes apparaissent dans `/studio` → « Ventes » (et `/admin` → « Ventes »). Un paiement
  reçu en main propre se valide avec « Marquer payée ».
- Après paiement, le client reçoit un lien privé `/commande/{lien}` (et un e-mail s'il l'a donné)
  pour télécharger ses originaux, une par une ou en ZIP. Le forfait couvre aussi les photos
  ajoutées plus tard.

### Paiement : KKiaPay ou FedaPay

Choisir le moyen de paiement dans `.env` avec `PAYMENT_DRIVER` :

- `fake` (par défaut, en local) : page de paiement simulée, aucun argent débité. Elle est
  désactivée automatiquement en production (`APP_ENV=production`).
- `kkiapay` : renseigner `KKIAPAY_PUBLIC_KEY`, `KKIAPAY_PRIVATE_KEY`, `KKIAPAY_SECRET`
  (tableau de bord KKiaPay), `KKIAPAY_SANDBOX=false` en réel. Webhook à déclarer dans KKiaPay :
  `https://votre-domaine/paiement/webhook/kkiapay` (secret dans `KKIAPAY_WEBHOOK_SECRET`).
- `fedapay` : renseigner `FEDAPAY_SECRET_KEY`, `FEDAPAY_ENVIRONMENT=live` en réel. Webhook :
  `https://votre-domaine/paiement/webhook/fedapay` (secret dans `FEDAPAY_WEBHOOK_SECRET`).

Le paiement n'est jamais validé sur la seule foi du navigateur : au retour du client comme à
la réception d'un webhook, le statut et le montant sont relus auprès de l'API du prestataire.
Tester d'abord en mode bac à sable (sandbox) avec les clés de test du prestataire.

## Tests

```bash
php artisan test                    # Laravel
cd face-service && pytest           # service IA
```

## Production

### LWS (hébergement mutualisé)

1. Créer une base **MySQL** dans le panneau LWS, puis remplir `.env` : `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL=https://votre-domaine`, `DB_CONNECTION=mysql` et `DB_*`,
   `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`,
   `QUEUE_FROM_SCHEDULER=true`, `MAIL_*` (boîte e-mail LWS), `PAYMENT_DRIVER` et ses clés.
2. Envoyer tout le dossier (avec `vendor/` et `public/build/`) par FTP ou le gestionnaire de
   fichiers, par exemple dans `trechart/`. Faire pointer le domaine vers `trechart/public`.
   Si LWS ne permet pas de changer la racine, le fichier `.htaccess` à la racine du projet
   redirige déjà toutes les requêtes vers `public/`.
3. Choisir PHP 8.3 avec les extensions `gd`, `intl`, `zip`, `exif`, `fileinfo`, `pdo_mysql`, et
   régler `upload_max_filesize` et `post_max_size` à 30M, `memory_limit` à 512M.
4. Par SSH (ou la console du panneau) : `php artisan key:generate` si la clé est vide, puis
   `php artisan app:deploy` (migrations + compte administrateur défini par `ADMIN_EMAIL` et
   `ADMIN_PASSWORD`) et `php artisan storage:link`.
5. Tâche planifiée (cron) LWS, chaque minute :
   `php /chemin/vers/trechart/artisan schedule:run`. Avec `QUEUE_FROM_SCHEDULER=true`, elle traite
   aussi les photos envoyées (miniatures, filigrane, originaux).
6. Reconnaissance faciale : un hébergement mutualisé ne peut pas faire tourner le service
   Python `face-service/`. L'installer sur un petit VPS (LWS VPS, Railway…) et renseigner
   `FACE_ENGINE_URL` et `FACE_ENGINE_TOKEN`. Sans lui, la galerie, les téléchargements et les
   ventes fonctionnent, mais pas la recherche par visage.

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
