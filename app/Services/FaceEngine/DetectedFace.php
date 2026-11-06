<?php

namespace App\Services\FaceEngine;

final readonly class DetectedFace
{
    public function __construct(
        public string $id,
        public int $x,
        public int $y,
        public int $w,
        public int $h,
        public float $detScore,
        public float $sharpness,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['face_id'],
            x: (int) $data['x'],
            y: (int) $data['y'],
            w: (int) $data['w'],
            h: (int) $data['h'],
            detScore: (float) ($data['det_score'] ?? 0),
            sharpness: (float) ($data['sharpness'] ?? 0),
        );
    }
}
