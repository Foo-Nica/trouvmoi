<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guest\Concerns\ResolvesEvent;
use App\Models\RemovalRequest;
use App\Services\Guests\GuestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** « Retirer cette photo » : demande traitée par le photographe sous 72 h. */
class RemovalRequestController extends Controller
{
    use ResolvesEvent;

    public function store(Request $request, string $slug, string $photoId, GuestSession $guests): RedirectResponse
    {
        $event = $this->accessibleEvent($slug, $guests);
        $photo = $this->eventPhoto($event, $photoId);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'contact' => ['nullable', 'string', 'max:120'],
        ]);

        RemovalRequest::firstOrCreate(
            ['event_id' => $event->id, 'photo_id' => $photo->id, 'status' => 'pending', 'guest_id' => $guests->current()?->id],
            ['reason' => $data['reason'] ?? null, 'contact' => $data['contact'] ?? null],
        );

        return back()->with('status', __('Demande envoyée. Le photographe retirera la photo sous 72 h.'));
    }
}
