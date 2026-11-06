<?php

namespace App\Services\FaceEngine;

final readonly class SelfieSearchResult
{
    /** @param list<FaceMatch> $matches */
    public function __construct(
        public array $matches,
        public bool $faceFound,
    ) {}
}
