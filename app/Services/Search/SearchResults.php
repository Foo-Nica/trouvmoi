<?php

namespace App\Services\Search;

use App\Models\Event;
use App\Models\Guest;
use App\Services\FaceEngine\FaceMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Résultats d'une recherche, gardés 2 h côté serveur le temps que l'invité
 * vérifie ses photos et crée son dossier. Seul l'invité qui a cherché y a accès.
 */
class SearchResults
{
    private const TTL_HOURS = 2;

    /**
     * @param  list<FaceMatch>  $matches
     * @param  list<string>  $queryFaceIds  visages de référence pour mettre le dossier à jour
     */
    public function put(Event $event, Guest $guest, string $mode, array $matches, array $queryFaceIds): string
    {
        $id = Str::random(32);

        Cache::put($this->key($id), [
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'mode' => $mode,
            'query_face_ids' => array_values(array_unique($queryFaceIds)),
            'matches' => array_map(fn (FaceMatch $m) => [
                'photo_id' => $m->photoId,
                'face_id' => $m->faceId,
                'similarity' => $m->similarity,
                'strong' => $m->strong,
            ], $matches),
        ], now()->addHours(self::TTL_HOURS));

        return $id;
    }

    public function get(string $id, Event $event, ?Guest $guest): ?array
    {
        $data = Cache::get($this->key($id));

        if (! $data || ! $guest || $data['event_id'] !== $event->id || $data['guest_id'] !== $guest->id) {
            return null;
        }

        return $data;
    }

    private function key(string $id): string
    {
        return 'search:'.$id;
    }
}
