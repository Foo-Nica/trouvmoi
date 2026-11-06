<?php

namespace App\Http\Controllers\Guest\Concerns;

use App\Enums\AccessMode;
use App\Models\Event;
use App\Models\Photo;
use App\Services\Guests\GuestSession;
use Illuminate\Database\Eloquent\Builder;

trait ResolvesEvent
{
    /** L'utilisateur connecté fait partie de l'équipe de l'événement (prévisualisation). */
    protected function isTeam(Event $event): bool
    {
        return auth()->check() && $event->isAccessibleBy(auth()->user());
    }

    protected function findEvent(string $slug): Event
    {
        return Event::where('slug', $slug)->with('owner')->firstOrFail();
    }

    /** Événement accessible et déverrouillé, sinon 404 / 403 (actions et JSON). */
    protected function accessibleEvent(string $slug, GuestSession $guests): Event
    {
        $event = $this->findEvent($slug);
        $team = $this->isTeam($event);

        abort_unless($event->isOnline() || $team, 404);
        abort_if($event->access_mode === AccessMode::Pin && ! $team && ! $guests->isUnlocked($event), 403);

        return $event;
    }

    /** La galerie complète est visible sauf en mode selfie (où seule la recherche est possible). */
    protected function galleryVisible(Event $event): bool
    {
        return $event->access_mode !== AccessMode::Selfie || ! $event->faceSearchEnabled();
    }

    protected function galleryQuery(Event $event): Builder
    {
        return Photo::query()
            ->where('event_id', $event->id)
            ->where('is_visible', true)
            ->whereNotNull('thumb_path')
            ->orderByRaw('COALESCE(taken_at, created_at)')
            ->orderBy('id');
    }

    protected function eventPhoto(Event $event, string $photoId): Photo
    {
        return Photo::where('event_id', $event->id)->where('is_visible', true)->whereNotNull('thumb_path')->findOrFail($photoId);
    }
}
