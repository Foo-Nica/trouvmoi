<?php

namespace App\Services\FaceEngine;

use App\Models\Event;
use App\Models\Photo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Client du service Python auto-hébergé (face-service/). */
class HttpFaceEngine implements FaceEngine
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeout = 60,
        private readonly int $searchLimit = 5000,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('trouvmoi.face_engine');

        return new self(rtrim($config['url'], '/'), $config['token'], $config['timeout'], $config['search_limit']);
    }

    private function http(): PendingRequest
    {
        if (blank($this->token)) {
            throw new FaceEngineException('FACE_ENGINE_TOKEN n\'est pas configuré.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function check(Response $response, string $action): Response
    {
        if ($response->failed()) {
            throw new FaceEngineException(sprintf(
                'Moteur facial : échec de %s (HTTP %d) %s',
                $action,
                $response->status(),
                (string) ($response->json('detail') ?? ''),
            ));
        }

        return $response;
    }

    private function send(callable $callback, string $action): Response
    {
        try {
            return $this->check($callback($this->http()), $action);
        } catch (ConnectionException $e) {
            throw new FaceEngineException("Moteur facial injoignable ({$action}) : {$e->getMessage()}", previous: $e);
        }
    }

    public function indexPhoto(Photo $photo, string $imageBytes): array
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http
                ->attach('image', $imageBytes, 'photo.jpg')
                ->post("/v1/events/{$photo->event_id}/photos/{$photo->id}/index"),
            'l\'indexation',
        );

        return array_map(DetectedFace::fromArray(...), $response->json('faces', []));
    }

    public function deletePhoto(Photo $photo): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete("/v1/events/{$photo->event_id}/photos/{$photo->id}"),
            'la suppression de photo');
    }

    public function deleteEvent(Event $event): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete("/v1/events/{$event->id}"),
            'la suppression de l\'événement');
    }

    public function searchByFaces(Event $event, array $faceIds, ?int $limit = null): array
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http->post("/v1/events/{$event->id}/search/faces", [
                'face_ids' => array_values($faceIds),
                'limit' => $limit ?? $this->searchLimit,
            ]),
            'la recherche',
        );

        return array_map(FaceMatch::fromArray(...), $response->json('matches', []));
    }

    public function searchBySelfie(Event $event, string $imageBytes, ?int $limit = null): SelfieSearchResult
    {
        try {
            $response = $this->http()
                ->attach('image', $imageBytes, 'selfie.jpg')
                ->post("/v1/events/{$event->id}/search/selfie?limit=".($limit ?? $this->searchLimit));
        } catch (ConnectionException $e) {
            throw new FaceEngineException("Moteur facial injoignable (selfie) : {$e->getMessage()}", previous: $e);
        }

        if ($response->status() === 422) {
            return new SelfieSearchResult([], faceFound: false);
        }

        $this->check($response, 'la recherche par selfie');

        return new SelfieSearchResult(array_map(FaceMatch::fromArray(...), $response->json('matches', [])), true);
    }
}
