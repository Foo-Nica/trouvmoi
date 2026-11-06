<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Photos\PhotoUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Réception des photos une par une (le navigateur envoie en parallèle et réessaie). */
class PhotoUploadController extends Controller
{
    public function __invoke(Request $request, Event $event, PhotoUploader $uploader): JsonResponse
    {
        $user = $request->user();
        // 404 plutôt que 403 : ne pas révéler l'existence de l'événement d'un autre photographe.
        abort_unless($event->isAccessibleBy($user), 404);

        $limit = $event->owner->planLimits()['photos_per_event'];
        if ($limit !== null && $event->photos_count >= $limit) {
            return response()->json([
                'message' => "Limite de {$limit} photos par événement atteinte pour votre forfait.",
            ], 422);
        }

        $request->validate([
            'file' => [
                'required', 'file',
                'mimes:'.implode(',', config('trouvmoi.upload.mimes')),
                'max:'.(config('trouvmoi.upload.max_mb') * 1024),
            ],
        ], [
            'file.mimes' => 'Format non accepté (JPG, PNG, HEIC ou WebP).',
            'file.max' => 'Photo trop lourde (:max Ko maximum).',
        ]);

        ['photo' => $photo, 'duplicate' => $duplicate] = $uploader->store($event, $user, $request->file('file'));

        return response()->json([
            'id' => $photo->id,
            'status' => $photo->status->value,
            'duplicate' => $duplicate,
        ], $duplicate ? 200 : 201);
    }
}
