<?php

namespace App\Jobs;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use App\Services\Images\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Miniature, aperçu et version téléchargeable, puis indexation des visages.
 * Avec $indexFaces à false (changement de filigrane), seules les images sont refaites.
 */
class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [10, 60];

    public function __construct(public Photo $photo, public bool $indexFaces = true)
    {
        $this->onQueue('photos');
    }

    public function handle(ImageProcessor $processor): void
    {
        $photo = $this->photo;
        $event = $photo->event;
        $disk = Storage::disk(Photo::disk());

        $watermark = $event->watermark_enabled
            ? $event->owner->displayName().' · '.config('trouvmoi.brand_domain')
            : null;

        $result = $processor->process($disk->get($photo->original_path), $watermark);

        $base = "events/{$event->id}";
        $paths = [
            'thumb_path' => "{$base}/thumbs/{$photo->id}.webp",
            'preview_path' => "{$base}/previews/{$photo->id}.webp",
            'download_path' => "{$base}/downloads/{$photo->id}.jpg",
        ];
        $disk->put($paths['thumb_path'], $result['thumb']);
        $disk->put($paths['preview_path'], $result['preview']);
        $disk->put($paths['download_path'], $result['download']);

        $photo->update($paths + [
            'width' => $result['width'],
            'height' => $result['height'],
            'status' => $this->indexFaces || $photo->status !== PhotoStatus::Indexed ? PhotoStatus::Processed : PhotoStatus::Indexed,
            'error' => null,
        ]);

        if ($this->indexFaces && $event->faceSearchEnabled()) {
            IndexPhotoFaces::dispatch($photo);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->photo->update([
            'status' => PhotoStatus::Failed,
            'error' => Str::limit('Traitement : '.$e->getMessage(), 500),
        ]);
    }
}
