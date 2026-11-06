<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guest\Concerns\ResolvesEvent;
use App\Models\Download;
use App\Models\Folder;
use App\Models\Photo;
use App\Services\Folders\FolderRefresher;
use App\Services\Guests\GuestSession;
use App\Services\Search\SearchResults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/** Dossier personnel de l'invité : création, consultation par lien privé, téléchargements. */
class FolderController extends Controller
{
    use ResolvesEvent;

    public function __construct(private readonly GuestSession $guests) {}

    public function store(Request $request, string $slug, SearchResults $results): RedirectResponse
    {
        $event = $this->accessibleEvent($slug, $this->guests);
        $data = $request->validate([
            'search' => ['required', 'string', 'size:32'],
            'photos' => ['array'],
            'photos.*' => ['string', 'max:26'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $search = $results->get($data['search'], $event, $this->guests->current());
        if (! $search) {
            return redirect()->route('event.show', $event->slug)
                ->with('status', __('Cette recherche a expiré. Relancez-la en un geste.'));
        }

        $matches = collect($search['matches'])->keyBy('photo_id');
        $selected = collect($data['photos'] ?? [])->filter(fn ($id) => $matches->has($id))->unique();

        if ($selected->isEmpty()) {
            return back()->withErrors(['photos' => __('Sélectionnez au moins une photo.')]);
        }

        $folder = Folder::create([
            'event_id' => $event->id,
            'guest_id' => $this->guests->current()->id,
            'name' => $data['name'] ?? null,
            'query_face_ids' => $search['query_face_ids'],
            'refreshed_at' => now(),
        ]);

        // Les photos proposées mais décochées sont gardées comme « Ce n'est pas moi » :
        // elles ne reviendront pas lors des mises à jour automatiques.
        $folder->photos()->attach($matches->mapWithKeys(fn ($m, $id) => [$id => [
            'similarity' => $m['similarity'],
            'source' => 'auto',
            'excluded' => ! $selected->contains($id),
        ]])->all());

        return redirect()->route('folder.show', $folder->access_token)->with('created', true);
    }

    public function show(string $token, FolderRefresher $refresher): View
    {
        $folder = $this->folder($token);

        if ($folder->event->isExpired()) {
            return view('guest.unavailable', ['event' => $folder->event]);
        }

        $added = $refresher->refreshIfStale($folder);

        $photos = $folder->keptPhotos()->whereNotNull('download_path')
            ->orderByRaw('COALESCE(photos.taken_at, photos.created_at)')->get();

        return view('guest.folder', [
            'folder' => $folder,
            'event' => $folder->event,
            'photos' => $photos,
            'isOwner' => $this->isOwner($folder),
            'added' => $added,
        ]);
    }

    public function exclude(string $token, string $photoId): RedirectResponse
    {
        $folder = $this->folder($token);
        abort_unless($this->isOwner($folder), 403);

        $folder->photos()->updateExistingPivot($photoId, ['excluded' => true]);

        return back()->with('status', __('Photo retirée de votre dossier.'));
    }

    public function destroy(string $token): RedirectResponse
    {
        $folder = $this->folder($token);
        abort_unless($this->isOwner($folder), 403);

        $slug = $folder->event->slug;
        $folder->delete();

        return redirect()->route('event.show', $slug)->with('status', __('Votre dossier a été supprimé.'));
    }

    public function download(string $token, string $photoId): StreamedResponse
    {
        $folder = $this->folder($token);
        abort_if($folder->event->isExpired(), 410);
        $photo = $folder->keptPhotos()->whereNotNull('download_path')->findOrFail($photoId);

        $this->logDownload($folder, 'photo', 1);

        return Storage::disk(Photo::disk())->download($photo->download_path, $this->fileName($folder, $photo));
    }

    public function zip(string $token): StreamedResponse
    {
        $folder = $this->folder($token);
        abort_if($folder->event->isExpired(), 410);
        $photos = $folder->keptPhotos()->whereNotNull('download_path')->get();
        abort_if($photos->isEmpty(), 404);

        $this->logDownload($folder, 'zip', $photos->count());
        $name = Str::slug($folder->event->title).'-trouvmoi.zip';

        return response()->streamDownload(function () use ($folder, $photos) {
            // Les JPEG sont déjà compressés : on les range sans recompresser (plus rapide).
            $zip = new ZipStream(sendHttpHeaders: false, defaultCompressionMethod: CompressionMethod::STORE, defaultEnableZeroHeader: true);
            $disk = Storage::disk(Photo::disk());

            foreach ($photos as $photo) {
                $stream = $disk->readStream($photo->download_path);
                if ($stream) {
                    $zip->addFileFromStream($this->fileName($folder, $photo), $stream);
                    fclose($stream);
                }
            }

            $zip->finish();
        }, $name, ['Content-Type' => 'application/zip']);
    }

    private function folder(string $token): Folder
    {
        return Folder::where('access_token', $token)->with('event.owner')->firstOrFail();
    }

    private function isOwner(Folder $folder): bool
    {
        return $this->guests->current()?->id === $folder->guest_id;
    }

    private function fileName(Folder $folder, Photo $photo): string
    {
        return Str::slug($folder->event->title).'-'.strtolower(substr($photo->id, -8)).'.jpg';
    }

    private function logDownload(Folder $folder, string $type, int $count): void
    {
        Download::create([
            'event_id' => $folder->event_id,
            'guest_id' => $this->guests->current()?->id,
            'folder_id' => $folder->id,
            'type' => $type,
            'photos_count' => $count,
        ]);
    }
}
