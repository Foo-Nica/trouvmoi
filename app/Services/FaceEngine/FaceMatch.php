<?php

namespace App\Services\FaceEngine;

final readonly class FaceMatch
{
    public function __construct(
        public string $photoId,
        public string $faceId,
        public float $similarity,
        /** true : « Vos photos » ; false : « Peut-être vous » */
        public bool $strong,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            photoId: (string) $data['photo_id'],
            faceId: (string) $data['face_id'],
            similarity: (float) $data['similarity'],
            strong: ($data['tier'] ?? 'maybe') === 'strong',
        );
    }
}
