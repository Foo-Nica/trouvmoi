<?php

use App\Http\Controllers\Guest\EventController;
use App\Http\Controllers\Guest\FaceSearchController;
use App\Http\Controllers\Guest\FolderController;
use App\Http\Controllers\Guest\OrderController;
use App\Http\Controllers\Guest\RemovalRequestController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Studio\EventQrController;
use App\Http\Controllers\Studio\PhotoUploadController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->middleware(SetLocale::class)->name('home');
Route::view('confidentialite', 'legal.privacy')->name('legal.privacy');
Route::view('conditions', 'legal.terms')->name('legal.terms');

// Espace photographe (hors pages Filament) : envoi des photos et QR codes.
Route::middleware(['auth', 'verified:filament.studio.auth.email-verification.prompt'])->prefix('studio')->name('studio.')->group(function () {
    Route::post('events/{event}/photos', PhotoUploadController::class)
        ->middleware('throttle:uploads')
        ->name('events.photos.store');
    Route::get('events/{event}/qr.{format}', EventQrController::class)
        ->whereIn('format', ['png', 'svg'])
        ->name('events.qr');
});

// Photos servies par liens signés et temporaires (disque local).
Route::get('media/{photo}/{variant}', MediaController::class)
    ->whereIn('variant', ['thumb', 'preview', 'download', 'original'])
    ->middleware('signed')
    ->name('media.show');

// Notifications des moyens de paiement (sans jeton CSRF, contenu revérifié auprès de l'API).
Route::post('paiement/webhook/{gateway}', PaymentWebhookController::class)
    ->whereIn('gateway', ['kkiapay', 'fedapay'])
    ->middleware('throttle:60,1')
    ->name('payment.webhook');

// Pages invités (accès par le QR code).
Route::middleware(SetLocale::class)->group(function () {
    Route::get('evenement', [EventController::class, 'find'])->middleware('throttle:20,1')->name('event.find');

    Route::prefix('e/{slug}')->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('event.show');
        Route::post('pin', [EventController::class, 'pin'])->name('event.pin');
        Route::post('consent', [EventController::class, 'consent'])->name('event.consent');
        Route::get('photos', [EventController::class, 'photos'])->name('event.photos');
        Route::get('p/{photoId}', [EventController::class, 'photo'])->name('event.photo');
        Route::get('p/{photoId}/download', [EventController::class, 'download'])
            ->middleware('throttle:120,60')->name('event.download');
        Route::get('p/{photoId}/original', [EventController::class, 'original'])
            ->middleware('throttle:120,60')->name('event.original');
        Route::post('panier/{photoId}', [OrderController::class, 'add'])->name('cart.add');
        Route::delete('panier/{photoId}', [OrderController::class, 'remove'])->name('cart.remove');
        Route::get('commande', [OrderController::class, 'checkout'])->name('order.checkout');
        Route::post('commande', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('order.store');
        Route::post('p/{photoId}/removal', [RemovalRequestController::class, 'store'])
            ->middleware('throttle:10,60')->name('event.removal');

        Route::middleware('throttle:face-search')->group(function () {
            Route::post('search/face', [FaceSearchController::class, 'byFace'])->name('event.search.face');
            Route::post('search/selfie', [FaceSearchController::class, 'bySelfie'])->name('event.search.selfie');
        });
        Route::get('r/{search}', [FaceSearchController::class, 'results'])->name('event.results');
        Route::post('folders', [FolderController::class, 'store'])->name('folder.store');
    });

    Route::prefix('d/{token}')->group(function () {
        Route::get('/', [FolderController::class, 'show'])->name('folder.show');
        Route::get('zip', [FolderController::class, 'zip'])->middleware('throttle:10,60')->name('folder.zip');
        Route::get('photo/{photoId}', [FolderController::class, 'download'])->name('folder.download');
        Route::post('photo/{photoId}/exclude', [FolderController::class, 'exclude'])->name('folder.exclude');
        Route::delete('/', [FolderController::class, 'destroy'])->name('folder.destroy');
    });

    // Commandes : lien privé donné après le paiement.
    Route::prefix('commande/{token}')->group(function () {
        Route::get('/', [OrderController::class, 'show'])->name('order.show');
        Route::get('retour', [OrderController::class, 'return'])->middleware('throttle:30,1')->name('order.return');
        Route::post('payer', [OrderController::class, 'retry'])->middleware('throttle:10,1')->name('order.retry');
        Route::get('photo/{photoId}', [OrderController::class, 'download'])->name('order.download');
        Route::get('zip', [OrderController::class, 'zip'])->middleware('throttle:10,60')->name('order.zip');
        Route::get('simulation', [OrderController::class, 'simulate'])->name('order.simulate');
        Route::post('simulation', [OrderController::class, 'simulateSubmit'])->name('order.simulate.submit');
    });
});
