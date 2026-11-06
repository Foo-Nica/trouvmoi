<?php

use App\Http\Controllers\Guest\EventController;
use App\Http\Controllers\Guest\FaceSearchController;
use App\Http\Controllers\Guest\FolderController;
use App\Http\Controllers\Guest\RemovalRequestController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Studio\EventQrController;
use App\Http\Controllers\Studio\PhotoUploadController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->middleware(SetLocale::class)->name('home');

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

// Pages invités (accès par le QR code).
Route::middleware(SetLocale::class)->group(function () {
    Route::prefix('e/{slug}')->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('event.show');
        Route::post('pin', [EventController::class, 'pin'])->name('event.pin');
        Route::post('consent', [EventController::class, 'consent'])->name('event.consent');
        Route::get('photos', [EventController::class, 'photos'])->name('event.photos');
        Route::get('p/{photoId}', [EventController::class, 'photo'])->name('event.photo');
        Route::get('p/{photoId}/download', [EventController::class, 'download'])
            ->middleware('throttle:120,60')->name('event.download');
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
});
