<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Folder;
use Illuminate\View\View;

/**
 * Pages publiques des invités. Lot 4 du cahier des charges : consentement, recherche par
 * visage, dossier et téléchargement viendront compléter ces pages.
 */
class GuestEventController extends Controller
{
    public function show(string $slug): View
    {
        $event = Event::where('slug', $slug)->with('owner')->firstOrFail();

        // Le photographe peut prévisualiser sa page avant la mise en ligne.
        $isTeam = auth()->check() && $event->isAccessibleBy(auth()->user());

        if (! $event->isOnline() && ! $isTeam) {
            return view('guest.unavailable', ['event' => $event]);
        }

        if (! $isTeam) {
            $event->increment('scans_count');
        }

        return view('guest.event', ['event' => $event]);
    }

    public function folder(string $token): View
    {
        $folder = Folder::where('access_token', $token)->with('event.owner')->firstOrFail();

        abort_if($folder->event->isExpired(), 410);

        return view('guest.folder', ['folder' => $folder]);
    }
}
