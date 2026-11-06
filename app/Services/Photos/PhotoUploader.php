<?php

namespace App\Services\Photos;

use App\Enums\PhotoStatus;
use App\Jobs\ProcessPhoto;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Enregistre une photo envoyée par un photographe et lance son traitement. */
class PhotoUploader
{
    /**
     * @return array{photo: Photo, duplicate: bool}
     */
    public function store(Event $event, User $user, UploadedFile $file): array
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        // Ré-envoyer un lot après une coupure ne crée pas de doublons.
        $existing = $event->photos()->where('checksum', $checksum)->first();
        if ($existing) {
            return ['photo' => $existing, 'duplicate' => true];
        }

        $id = (string) Str::ulid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $path = $file->storeAs("events/{$event->id}/originals", "{$id}.{$extension}", ['disk' => Photo::disk()]);

        $photo = DB::transaction(function () use ($event, $user, $file, $id, $path, $checksum) {
            $photo = new Photo([
                'event_id' => $event->id,
                'uploaded_by' => $user->id,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'original_path' => $path,
                'size_bytes' => $file->getSize(),
                'checksum' => $checksum,
                'status' => PhotoStatus::Received,
                'taken_at' => $this->takenAt($file),
            ]);
            $photo->id = $id;
            $photo->save();
            $event->increment('photos_count');

            return $photo;
        });

        ProcessPhoto::dispatch($photo);

        return ['photo' => $photo, 'duplicate' => false];
    }

    /** Date de prise de vue (EXIF) pour trier la galerie dans l'ordre de l'événement. */
    private function takenAt(UploadedFile $file): ?string
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return null;
        }

        $exif = @exif_read_data($file->getRealPath());
        $value = $exif['DateTimeOriginal'] ?? null;
        if (! $value) {
            return null;
        }

        $date = \DateTime::createFromFormat('Y:m:d H:i:s', $value);

        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}
