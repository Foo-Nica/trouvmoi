<?php

namespace App\Services\Folders;

use App\Models\Folder;
use App\Services\FaceEngine\FaceEngine;
use App\Services\FaceEngine\FaceEngineException;
use Illuminate\Support\Facades\Log;

/**
 * Ajoute au dossier les nouvelles photos de l'invité envoyées après sa recherche.
 * Seules les correspondances sûres sont ajoutées ; les photos retirées (« Ce n'est pas moi »)
 * ne reviennent jamais.
 */
class FolderRefresher
{
    public function __construct(private readonly FaceEngine $engine) {}

    public function refreshIfStale(Folder $folder): int
    {
        $event = $folder->event;

        if (! $event->faceSearchEnabled() || empty($folder->query_face_ids)) {
            return 0;
        }

        $lastIndexed = $event->photos()->where('status', 'indexed')->max('updated_at');
        if (! $lastIndexed || ($folder->refreshed_at && $folder->refreshed_at->gte($lastIndexed))) {
            return 0;
        }

        try {
            $matches = $this->engine->searchByFaces($event, $folder->query_face_ids);
        } catch (FaceEngineException $e) {
            Log::warning('Mise à jour du dossier impossible', ['folder' => $folder->id, 'error' => $e->getMessage()]);

            return 0;
        }

        $known = $folder->photos()->pluck('photos.id')->all();
        $visible = $event->photos()->where('is_visible', true)->pluck('id')->flip();

        $new = collect($matches)
            ->filter(fn ($m) => $m->strong && ! in_array($m->photoId, $known, true) && $visible->has($m->photoId))
            ->mapWithKeys(fn ($m) => [$m->photoId => ['similarity' => $m->similarity, 'source' => 'auto', 'excluded' => false]]);

        if ($new->isNotEmpty()) {
            $folder->photos()->attach($new->all());
        }

        $folder->forceFill(['refreshed_at' => now()])->save();

        return $new->count();
    }
}
