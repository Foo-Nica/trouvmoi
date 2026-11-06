<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guest\Concerns\ResolvesEvent;
use App\Models\Event;
use App\Models\Face;
use App\Models\FaceSearch;
use App\Models\Photo;
use App\Services\FaceEngine\FaceEngine;
use App\Services\FaceEngine\FaceEngineException;
use App\Services\FaceEngine\FaceMatch;
use App\Services\Guests\GuestSession;
use App\Services\Search\SearchResults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Recherche des photos d'un invité : visage touché dans la galerie, ou selfie. */
class FaceSearchController extends Controller
{
    use ResolvesEvent;

    public function __construct(
        private readonly FaceEngine $engine,
        private readonly GuestSession $guests,
        private readonly SearchResults $results,
    ) {}

    public function byFace(Request $request, string $slug): RedirectResponse
    {
        $event = $this->searchableEvent($slug);
        abort_unless($this->galleryVisible($event), 404);

        $data = $request->validate(['face_id' => ['required', 'string', 'max:64']]);
        $face = Face::where('event_id', $event->id)->where('external_id', $data['face_id'])->firstOrFail();

        if (! $this->guests->hasConsented($event)) {
            return redirect()->route('event.photo', [$event->slug, $face->photo_id])
                ->with('needs_consent', true);
        }

        return $this->run($event, 'gallery', $face->external_id,
            fn () => $this->engine->searchByFaces($event, [$face->external_id]),
            fallback: route('event.photo', [$event->slug, $face->photo_id]));
    }

    public function bySelfie(Request $request, string $slug): RedirectResponse
    {
        $event = $this->searchableEvent($slug);

        if (! $this->guests->hasConsented($event)) {
            return redirect()->route('event.show', $event->slug)->with('needs_consent', true);
        }

        $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
        ], [
            'selfie.required' => __('Prenez un selfie pour lancer la recherche.'),
            'selfie.mimes' => __('Format de photo non accepté.'),
            'selfie.max' => __('Photo trop lourde (10 Mo maximum).'),
        ]);

        $bytes = $request->file('selfie')->get();

        return $this->run($event, 'selfie', null, function () use ($event, $bytes) {
            $result = $this->engine->searchBySelfie($event, $bytes);

            return $result->faceFound ? $result->matches : null;
        }, fallback: route('event.show', $event->slug).'#rechercher');
    }

    public function results(string $slug, string $search): View|RedirectResponse
    {
        $event = $this->searchableEvent($slug);
        $data = $this->results->get($search, $event, $this->guests->current());

        if (! $data) {
            return redirect()->route('event.show', $event->slug)
                ->with('status', __('Cette recherche a expiré. Relancez-la en un geste.'));
        }

        $photos = Photo::where('event_id', $event->id)
            ->where('is_visible', true)
            ->whereIn('id', array_column($data['matches'], 'photo_id'))
            ->get()
            ->keyBy('id');

        $items = collect($data['matches'])
            ->filter(fn ($m) => $photos->has($m['photo_id']))
            ->map(fn ($m) => $m + [
                'thumb' => $photos[$m['photo_id']]->temporaryUrl('thumb', 120),
                'ratio' => $photos[$m['photo_id']]->width ? round($photos[$m['photo_id']]->width / $photos[$m['photo_id']]->height, 4) : 1.5,
            ])
            ->values();

        return view('guest.results', [
            'event' => $event,
            'search' => $search,
            'strong' => $items->where('strong', true)->values(),
            'maybe' => $items->where('strong', false)->values(),
        ]);
    }

    private function searchableEvent(string $slug): Event
    {
        $event = $this->accessibleEvent($slug, $this->guests);
        abort_unless($event->faceSearchEnabled(), 404);

        return $event;
    }

    /**
     * @param  callable(): (list<FaceMatch>|null)  $search  null = aucun visage sur le selfie
     */
    private function run(Event $event, string $mode, ?string $sourceFaceId, callable $search, string $fallback): RedirectResponse
    {
        $guest = $this->guests->ensure();
        $started = microtime(true);

        try {
            $matches = $search();
        } catch (FaceEngineException $e) {
            Log::warning('Recherche faciale impossible', ['event' => $event->id, 'error' => $e->getMessage()]);

            return redirect()->to($fallback)->withErrors(['search' => __('La recherche est momentanément indisponible. Réessayez dans un instant.')]);
        }

        if ($matches === null) {
            return redirect()->to($fallback)->withErrors(['selfie' => __('Aucun visage détecté. Reprenez un selfie de face, bien éclairé.')]);
        }

        FaceSearch::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'mode' => $mode,
            'source_face_id' => $sourceFaceId,
            'results_count' => count($matches),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        // Références pour la mise à jour automatique du dossier : le visage touché
        // et les meilleures correspondances (le selfie, lui, n'est jamais conservé).
        $strongFaces = array_map(fn (FaceMatch $m) => $m->faceId, array_filter($matches, fn (FaceMatch $m) => $m->strong));
        $references = array_slice(array_values(array_filter([$sourceFaceId, ...$strongFaces])), 0, 5);

        $id = $this->results->put($event, $guest, $mode, $matches, $references);

        return redirect()->route('event.results', [$event->slug, $id]);
    }
}
