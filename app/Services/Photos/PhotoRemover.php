<?php

namespace App\Services\Photos;

use App\Models\Photo;
use App\Services\FaceEngine\FaceEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Supprime une photo partout : fichiers, visages (base et moteur IA), compteurs. */
class PhotoRemover
{
    public function __construct(private readonly FaceEngine $engine) {}

    public function remove(Photo $photo): void
    {
        if ($photo->faces_count > 0) {
            $this->engine->deletePhoto($photo);
        }

        Storage::disk(Photo::disk())->delete(array_filter([
            $photo->original_path, $photo->thumb_path, $photo->preview_path, $photo->download_path,
        ]));

        DB::transaction(function () use ($photo) {
            $event = $photo->event;
            $event->decrement('photos_count');
            if ($photo->faces_count > 0) {
                $event->decrement('faces_count', $photo->faces_count);
            }
            $photo->delete();
        });
    }
}
