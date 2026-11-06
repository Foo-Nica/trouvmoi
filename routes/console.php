<?php

use App\Jobs\PurgeEventFaces;
use App\Models\Event;
use Illuminate\Support\Facades\Schedule;

// Données faciales supprimées automatiquement à l'expiration des événements.
Schedule::call(function () {
    Event::query()
        ->whereNull('faces_purged_at')
        ->where('expires_at', '<=', now())
        ->each(fn (Event $event) => PurgeEventFaces::dispatch($event));
})->dailyAt('03:00')->name('purge-expired-events')->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
