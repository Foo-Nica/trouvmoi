<?php

use Illuminate\Support\Facades\Schedule;

// Durée de conservation : données faciales puis photos des événements expirés.
Schedule::command('events:purge-expired')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes()->when(fn (): bool => config('queue.default') === 'redis');

// Hébergement mutualisé (LWS…) sans processus permanent : la tâche cron « schedule:run »
// traite aussi la file d'attente chaque minute. Activer avec QUEUE_FROM_SCHEDULER=true.
if (config('trouvmoi.queue_from_scheduler')) {
    Schedule::command('queue:work --queue=photos,faces,default --stop-when-empty --max-time=55 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(5);
}
