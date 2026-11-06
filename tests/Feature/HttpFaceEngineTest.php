<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Photo;
use App\Services\FaceEngine\FaceEngineException;
use App\Services\FaceEngine\HttpFaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpFaceEngineTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): HttpFaceEngine
    {
        return new HttpFaceEngine('http://face.test', 'secret', 5, 100);
    }

    public function test_index_sends_image_with_token_and_maps_faces(): void
    {
        Http::fake(['face.test/*' => Http::response(['faces' => [
            ['face_id' => 'abc', 'x' => 1, 'y' => 2, 'w' => 80, 'h' => 90, 'det_score' => 0.9, 'sharpness' => 50],
        ]])]);
        $photo = Photo::factory()->create();

        $faces = $this->engine()->indexPhoto($photo, 'jpeg-bytes');

        $this->assertSame('abc', $faces[0]->id);
        $this->assertSame(80, $faces[0]->w);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret')
            && $r->url() === "http://face.test/v1/events/{$photo->event_id}/photos/{$photo->id}/index");
    }

    public function test_search_maps_tiers(): void
    {
        Http::fake(['face.test/*' => Http::response(['matches' => [
            ['photo_id' => 'p1', 'face_id' => 'f1', 'similarity' => 0.71, 'tier' => 'strong'],
            ['photo_id' => 'p2', 'face_id' => 'f2', 'similarity' => 0.39, 'tier' => 'maybe'],
        ]])]);

        $matches = $this->engine()->searchByFaces(Event::factory()->create(), ['f0']);

        $this->assertTrue($matches[0]->strong);
        $this->assertFalse($matches[1]->strong);
        Http::assertSent(fn (Request $r) => $r['face_ids'] === ['f0'] && $r['limit'] === 100);
    }

    public function test_selfie_without_face_is_not_an_error(): void
    {
        Http::fake(['face.test/*' => Http::response(['detail' => 'Aucun visage'], 422)]);

        $result = $this->engine()->searchBySelfie(Event::factory()->create(), 'bytes');

        $this->assertFalse($result->faceFound);
    }

    public function test_server_errors_raise_a_clear_exception(): void
    {
        Http::fake(['face.test/*' => Http::response(['detail' => 'boom'], 500)]);

        $this->expectException(FaceEngineException::class);
        $this->engine()->deleteEvent(Event::factory()->create());
    }

    public function test_missing_token_is_refused(): void
    {
        $this->expectException(FaceEngineException::class);
        (new HttpFaceEngine('http://face.test', null))->deleteEvent(Event::factory()->create());
    }
}
