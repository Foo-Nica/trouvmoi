<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/** Supprime tous les fichiers d'un événement (photos, miniatures, couverture). */
class DeleteEventFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $eventId, public ?string $coverPath = null) {}

    public function handle(): void
    {
        $disk = Storage::disk(Photo::disk());
        $disk->deleteDirectory("events/{$this->eventId}");

        if ($this->coverPath) {
            $disk->delete($this->coverPath);
        }
    }
}
