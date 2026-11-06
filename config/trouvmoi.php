<?php

return [

    /*
    | Disque des photos : « r2 » ou « s3 » en production, « local » en développement.
    */
    'photos_disk' => env('PHOTOS_DISK', 'local'),

    'upload' => [
        'max_mb' => (int) env('UPLOAD_MAX_MB', 25),
        'mimes' => ['jpg', 'jpeg', 'png', 'heic', 'heif', 'webp'],
    ],

    'images' => [
        'thumb_size' => 400,
        'preview_size' => 1600,
        'webp_quality' => 80,
    ],

    /*
    | Service de reconnaissance faciale auto-hébergé (dossier face-service/).
    */
    'face_engine' => [
        'url' => env('FACE_ENGINE_URL', 'http://127.0.0.1:8001'),
        'token' => env('FACE_ENGINE_TOKEN'),
        'timeout' => (int) env('FACE_ENGINE_TIMEOUT', 60),
        'search_limit' => 5000,
    ],

    /*
    | Version de la notice de consentement : la changer redemande le consentement.
    */
    'consent_notice_version' => '2026-11',

    'default_retention_days' => 60,

    // Domaine affiché dans le filigrane et sur les affiches QR.
    'brand_domain' => env('BRAND_DOMAIN', 'trouvmoi.app'),

    /*
    | Forfaits mensuels des photographes. Prix mensuels en FCFA (XOF).
    | null = illimité.
    */
    'plans' => [
        'trial' => ['label' => 'Essai', 'price_xof' => 0, 'events_per_month' => 1, 'photos_per_event' => 300],
        'starter' => ['label' => 'Starter', 'price_xof' => 2000, 'events_per_month' => 4, 'photos_per_event' => 3000],
        'pro' => ['label' => 'Pro', 'price_xof' => 5000, 'events_per_month' => 15, 'photos_per_event' => 15000],
        'studio' => ['label' => 'Studio', 'price_xof' => 15000, 'events_per_month' => null, 'photos_per_event' => 50000],
    ],

    'locales' => [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
    ],

];
