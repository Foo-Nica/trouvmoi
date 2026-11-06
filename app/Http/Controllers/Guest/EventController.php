<?php

namespace App\Http\Controllers\Guest;

use App\Enums\AccessMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Guest\Concerns\ResolvesEvent;
use App\Models\Download;
use App\Models\Photo;
use App\Services\Guests\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Page d'un événement côté invité : accueil, PIN, consentement, galerie, visionneuse. */
class EventController extends Controller
{
    use ResolvesEvent;

    private const PER_PAGE = 30;

    public function show(string $slug, GuestSession $guests): View
    {
        $event = $this->findEvent($slug);
        $team = $this->isTeam($event);

        if (! $event->isOnline() && ! $team) {
            return view('guest.unavailable', ['event' => $event]);
        }

        if ($event->access_mode === AccessMode::Pin && ! $team && ! $guests->isUnlocked($event)) {
            return view('guest.pin', ['event' => $event]);
        }

        if (! $team && ! session()->has("scanned.{$event->id}")) {
            session()->put("scanned.{$event->id}", true);
            $event->increment('scans_count');
        }

        $guest = $guests->current();

        return view('guest.event', [
            'event' => $event,
            'galleryVisible' => $this->galleryVisible($event),
            'faceSearch' => $event->faceSearchEnabled(),
            'consented' => $guests->hasConsented($event),
            'folders' => $guest ? $guest->folders()->where('event_id', $event->id)->latest()->get() : collect(),
            'firstPage' => $this->galleryVisible($event) ? $this->page($event, 1) : null,
        ]);
    }

    public function pin(Request $request, string $slug, GuestSession $guests): RedirectResponse
    {
        $event = $this->findEvent($slug);
        abort_unless($event->access_mode === AccessMode::Pin && $event->isOnline(), 404);

        $key = "pin:{$event->id}:{$request->ip()}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pin' => __('Trop d\'essais. Réessayez dans une minute.')]);
        }

        $request->validate(['pin' => ['required', 'string', 'max:8']]);

        if (! $event->checkPin($request->string('pin'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['pin' => __('Code incorrect.')]);
        }

        RateLimiter::clear($key);
        $guests->unlock($event);

        return redirect()->route('event.show', $event->slug);
    }

    public function consent(Request $request, string $slug, GuestSession $guests): RedirectResponse
    {
        $event = $this->accessibleEvent($slug, $guests);
        abort_unless($event->faceSearchEnabled(), 404);

        $request->validate(['accept' => ['accepted']], ['accept.accepted' => __('Cochez la case pour continuer.')]);
        $guests->recordConsent($event);

        $next = $request->input('next');

        return redirect()->to(is_string($next) && str_starts_with($next, '/e/'.$event->slug)
            ? $next
            : route('event.show', $event->slug).'#rechercher');
    }

    /** Page suivante de la galerie (défilement infini). */
    public function photos(Request $request, string $slug, GuestSession $guests): JsonResponse
    {
        $event = $this->accessibleEvent($slug, $guests);
        abort_unless($this->galleryVisible($event), 404);

        return response()->json($this->page($event, max(1, (int) $request->query('page', 1))));
    }

    public function photo(string $slug, string $photoId, GuestSession $guests): View
    {
        $event = $this->accessibleEvent($slug, $guests);
        abort_unless($this->galleryVisible($event), 404);
        $photo = $this->eventPhoto($event, $photoId);

        $faces = $event->faceSearchEnabled() && $photo->width
            ? $photo->faces()->get()->map(fn ($face) => [
                'id' => $face->external_id,
                'left' => round($face->x / $photo->width * 100, 3),
                'top' => round($face->y / $photo->height * 100, 3),
                'width' => round($face->w / $photo->width * 100, 3),
                'height' => round($face->h / $photo->height * 100, 3),
            ])->values()
            : collect();

        return view('guest.photo', [
            'event' => $event,
            'photo' => $photo,
            'faces' => $faces,
            'consented' => $guests->hasConsented($event),
            'faceSearch' => $event->faceSearchEnabled(),
        ]);
    }

    /** Téléchargement d'une photo depuis la galerie (version filigranée si activé). */
    public function download(string $slug, string $photoId, GuestSession $guests): StreamedResponse
    {
        $event = $this->accessibleEvent($slug, $guests);
        abort_unless($this->galleryVisible($event), 404);
        $photo = $this->eventPhoto($event, $photoId);
        abort_unless($photo->download_path, 404);

        Download::create(['event_id' => $event->id, 'guest_id' => $guests->current()?->id, 'type' => 'photo', 'photos_count' => 1]);

        return Storage::disk(Photo::disk())->download($photo->download_path, Str::slug($event->title).'-'.strtolower(substr($photo->id, -8)).'.jpg');
    }

    /** @return array{photos: list<array>, next: int|null, total: int} */
    private function page($event, int $page): array
    {
        $paginator = $this->galleryQuery($event)->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'photos' => $paginator->getCollection()->map(fn (Photo $p) => [
                'id' => $p->id,
                'thumb' => $p->temporaryUrl('thumb', 120),
                'url' => route('event.photo', [$event->slug, $p->id]),
                'ratio' => $p->width && $p->height ? round($p->width / $p->height, 4) : 1.5,
            ])->all(),
            'next' => $paginator->hasMorePages() ? $page + 1 : null,
            'total' => $paginator->total(),
        ];
    }
}
