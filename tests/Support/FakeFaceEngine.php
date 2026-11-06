<?php

namespace Tests\Support;

use App\Models\Event;
use App\Models\Photo;
use App\Services\FaceEngine\DetectedFace;
use App\Services\FaceEngine\FaceEngine;
use App\Services\FaceEngine\FaceMatch;
use App\Services\FaceEngine\SelfieSearchResult;
use Illuminate\Support\Str;

/** Moteur facial de test : renvoie un nombre fixe de visages par photo. */
class FakeFaceEngine implements FaceEngine
{
    public array $indexed = [];

    public array $deletedPhotos = [];

    public array $deletedEvents = [];

    /** @var list<FaceMatch> résultats renvoyés par les recherches */
    public array $matches = [];

    public bool $selfieHasFace = true;

    public array $searches = [];

    public function __construct(public int $facesPerPhoto = 2) {}

    public function indexPhoto(Photo $photo, string $imageBytes): array
    {
        $this->indexed[] = $photo->id;

        return array_map(fn (int $i) => new DetectedFace(Str::random(32), 100 * $i, 50, 80, 90, 0.95, 60.0),
            range(0, $this->facesPerPhoto - 1));
    }

    public function deletePhoto(Photo $photo): void
    {
        $this->deletedPhotos[] = $photo->id;
    }

    public function deleteEvent(Event $event): void
    {
        $this->deletedEvents[] = $event->id;
    }

    public function searchByFaces(Event $event, array $faceIds, ?int $limit = null): array
    {
        $this->searches[] = ['faces', $faceIds];

        return $this->matches;
    }

    public function searchBySelfie(Event $event, string $imageBytes, ?int $limit = null): SelfieSearchResult
    {
        $this->searches[] = ['selfie', strlen($imageBytes)];

        return new SelfieSearchResult($this->selfieHasFace ? $this->matches : [], $this->selfieHasFace);
    }
}
