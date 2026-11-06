<?php

namespace App\Services\FaceEngine;

use App\Models\Event;
use App\Models\Photo;

/**
 * Contrat du moteur de reconnaissance faciale.
 * Implémentation actuelle : HttpFaceEngine (service Python auto-hébergé).
 */
interface FaceEngine
{
    /** @return list<DetectedFace> */
    public function indexPhoto(Photo $photo, string $imageBytes): array;

    public function deletePhoto(Photo $photo): void;

    public function deleteEvent(Event $event): void;

    /**
     * Mode galerie : recherche à partir de visages déjà indexés.
     *
     * @param  list<string>  $faceIds  identifiants externes des visages
     * @return list<FaceMatch>
     */
    public function searchByFaces(Event $event, array $faceIds, ?int $limit = null): array;

    /** Mode selfie : le selfie est analysé puis oublié par le service. */
    public function searchBySelfie(Event $event, string $imageBytes, ?int $limit = null): SelfieSearchResult;
}
