<?php

namespace App\Jobs;

use App\Enums\PhotoStatus;
use App\Models\Face;
use App\Models\Photo;
use App\Services\FaceEngine\FaceEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Envoie la photo d'origine au moteur facial et enregistre les cadres des visages. */
class IndexPhotoFaces implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 120;

    public array $backoff = [15, 60, 300];

    public function __construct(public Photo $photo)
    {
        $this->onQueue('faces');
    }

    public function handle(FaceEngine $engine): void
    {
        $photo = $this->photo->fresh();
        if (! $photo || ! $photo->event->faceSearchEnabled()) {
            return;
        }

        $faces = $engine->indexPhoto($photo, Storage::disk(Photo::disk())->get($photo->original_path));

        DB::transaction(function () use ($photo, $faces) {
            $previous = $photo->faces()->count();
            $photo->faces()->delete();

            $now = now();
            Face::insert(array_map(fn ($face) => [
                'photo_id' => $photo->id,
                'event_id' => $photo->event_id,
                'external_id' => $face->id,
                'x' => $face->x,
                'y' => $face->y,
                'w' => $face->w,
                'h' => $face->h,
                'det_score' => $face->detScore,
                'sharpness' => $face->sharpness,
                'created_at' => $now,
                'updated_at' => $now,
            ], $faces));

            $photo->update(['faces_count' => count($faces), 'status' => PhotoStatus::Indexed, 'error' => null]);
            $photo->event()->increment('faces_count', count($faces) - $previous);
        });
    }

    public function failed(Throwable $e): void
    {
        $this->photo->update([
            'status' => PhotoStatus::Failed,
            'error' => Str::limit('Reconnaissance faciale : '.$e->getMessage(), 500),
        ]);
    }
}
