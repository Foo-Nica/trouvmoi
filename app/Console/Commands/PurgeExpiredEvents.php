<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Jobs\DeleteEventFiles;
use App\Jobs\PurgeEventFaces;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Application de la durée de conservation :
 * 1. à l'expiration, les données faciales sont supprimées et l'événement est archivé ;
 * 2. après le délai de grâce, les photos elles-mêmes sont supprimées.
 */
#[Signature('events:purge-expired')]
#[Description('Supprime les données faciales puis les photos des événements expirés')]
class PurgeExpiredEvents extends Command
{
    public function handle(): int
    {
        $faces = 0;
        Event::query()
            ->whereNull('faces_purged_at')
            ->where('expires_at', '<=', now())
            ->each(function (Event $event) use (&$faces) {
                $event->forceFill(['status' => EventStatus::Archived])->save();
                PurgeEventFaces::dispatch($event);
                $faces++;
            });

        $graceDays = (int) config('trouvmoi.photos_grace_days');
        $photos = 0;
        Event::query()
            ->whereNotNull('faces_purged_at')
            ->where('expires_at', '<=', now()->subDays($graceDays))
            ->where('photos_count', '>', 0)
            ->each(function (Event $event) use (&$photos) {
                $event->photos()->delete();
                $event->forceFill(['photos_count' => 0])->save();
                DeleteEventFiles::dispatch($event->id);
                $photos++;
            });

        $this->info("Données faciales supprimées : {$faces} événement(s). Photos supprimées : {$photos} événement(s).");

        return self::SUCCESS;
    }
}
