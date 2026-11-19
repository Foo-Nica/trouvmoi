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

    // Hébergement mutualisé : la file d'attente est traitée par la tâche cron (voir routes/console.php).
    'queue_from_scheduler' => (bool) env('QUEUE_FROM_SCHEDULER', false),

    // Après l'expiration (données faciales déjà supprimées), délai avant la suppression des photos.
    'photos_grace_days' => (int) env('PHOTOS_GRACE_DAYS', 30),

    /*
    | Limites par compte. Le site est réservé à l'équipe TRECHART : tous les comptes
    | utilisent « studio » (événements illimités). Les autres paliers restent disponibles
    | pour limiter un compte précis si besoin. null = illimité.
    */
    'plans' => [
        'trial' => ['label' => 'Essai', 'price_xof' => 0, 'events_per_month' => 1, 'photos_per_event' => 300],
        'starter' => ['label' => 'Starter', 'price_xof' => 2000, 'events_per_month' => 4, 'photos_per_event' => 3000],
        'pro' => ['label' => 'Pro', 'price_xof' => 5000, 'events_per_month' => 15, 'photos_per_event' => 15000],
        'studio' => ['label' => 'Studio', 'price_xof' => 15000, 'events_per_month' => null, 'photos_per_event' => 50000],
    ],

    /*
    | Paiement des photos (versions originales, personnalités).
    | driver : « kkiapay », « fedapay » ou « fake » (démonstration locale : aucun débit réel).
    */
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),
        'currency' => 'XOF',

        'kkiapay' => [
            'public_key' => env('KKIAPAY_PUBLIC_KEY'),
            'private_key' => env('KKIAPAY_PRIVATE_KEY'),
            'secret' => env('KKIAPAY_SECRET'),
            'sandbox' => (bool) env('KKIAPAY_SANDBOX', true),
            // Valeur saisie dans le tableau de bord KKiaPay pour signer le webhook (en-tête x-kkiapay-secret).
            'webhook_secret' => env('KKIAPAY_WEBHOOK_SECRET'),
            'api_url' => env('KKIAPAY_API_URL', 'https://api.kkiapay.me'),
            'sandbox_api_url' => env('KKIAPAY_SANDBOX_API_URL', 'https://api-sandbox.kkiapay.me'),
            'widget_url' => 'https://cdn.kkiapay.me/k.js',
        ],

        'fedapay' => [
            'secret_key' => env('FEDAPAY_SECRET_KEY'),
            'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'), // sandbox | live
            'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
            'api_url' => 'https://api.fedapay.com/v1',
            'sandbox_api_url' => 'https://sandbox-api.fedapay.com/v1',
            'country' => env('FEDAPAY_COUNTRY', 'bj'),
        ],
    ],

    'locales' => [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
    ],

    /*
    | Compte administrateur créé ou mis à jour par « php artisan app:deploy » (mise en production).
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Mentions des pages légales (/confidentialite et /conditions).
    */
    'legal' => [
        'company' => env('LEGAL_COMPANY', 'TRECHART EMPIRE'),
        'address' => env('LEGAL_ADDRESS', 'Bénin'),
        'email' => env('LEGAL_EMAIL', 'contact@trechart.com'),
        'apdp_reference' => env('LEGAL_APDP_REFERENCE'),
        'updated_at' => '2026-11-19',
    ],

];
