<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\FaceEngine\FaceEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Supprime toutes les données faciales d'un événement (expiration, mineurs, suppression). */
class PurgeEventFaces implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900];

    public function __construct(public Event $event, public bool $markPurged = true) {}

    public function handle(FaceEngine $engine): void
    {
        $engine->deleteEvent($this->event);

        DB::transaction(function () {
            $this->event->faces()->delete();
            $this->event->photos()->update(['faces_count' => 0]);
            $this->event->forceFill([
                'faces_count' => 0,
                'faces_purged_at' => $this->markPurged ? now() : null,
            ])->save();
        });
    }
}
