<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une photo depuis un disque sans URL temporaire native (disque local en développement).
 * La route est signée et expire : impossible de deviner l'adresse d'une photo.
 */
class MediaController extends Controller
{
    public function __invoke(Photo $photo, string $variant): StreamedResponse
    {
        $path = match ($variant) {
            'original' => $photo->original_path,
            'preview' => $photo->preview_path,
            'download' => $photo->download_path,
            default => $photo->thumb_path,
        };

        abort_unless($path && Storage::disk(Photo::disk())->exists($path), 404);

        return Storage::disk(Photo::disk())->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
