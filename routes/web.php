<?php

use App\Http\Controllers\GuestEventController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Studio\EventQrController;
use App\Http\Controllers\Studio\PhotoUploadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

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
Route::get('e/{slug}', [GuestEventController::class, 'show'])->name('event.show');
Route::get('d/{token}', [GuestEventController::class, 'folder'])->name('folder.show');
