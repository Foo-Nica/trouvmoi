<?php

namespace Tests\Feature;

use App\Enums\PhotoStatus;
use App\Jobs\IndexPhotoFaces;
use App\Jobs\ProcessPhoto;
use App\Jobs\PurgeEventFaces;
use App\Models\Event;
use App\Models\Photo;
use App\Services\Photos\PhotoRemover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function photo(array $eventAttributes = []): Photo
    {
        $event = Event::factory()->create($eventAttributes);
        $path = "events/{$event->id}/originals/test.jpg";
        Storage::disk(Photo::disk())->put($path, $this->jpeg(3000, 2000));

        return Photo::factory()->for($event)->create(['original_path' => $path]);
    }

    public function test_processing_creates_variants_and_queues_indexing(): void
    {
        Queue::fake([IndexPhotoFaces::class]);
        $photo = $this->photo();

        ProcessPhoto::dispatchSync($photo);

        $photo->refresh();
        $this->assertSame(PhotoStatus::Processed, $photo->status);
        $this->assertSame([3000, 2000], [$photo->width, $photo->height]);
        foreach ([$photo->thumb_path, $photo->preview_path, $photo->download_path] as $path) {
            Storage::disk(Photo::disk())->assertExists($path);
        }
        [$w, $h] = getimagesizefromstring(Storage::disk(Photo::disk())->get($photo->thumb_path));
        $this->assertSame(400, max($w, $h));
        Queue::assertPushed(IndexPhotoFaces::class);
    }

    public function test_events_with_minors_are_never_indexed(): void
    {
        Queue::fake([IndexPhotoFaces::class]);

        ProcessPhoto::dispatchSync($this->photo(['has_minors' => true]));

        Queue::assertNotPushed(IndexPhotoFaces::class);
    }

    public function test_indexing_stores_faces_and_counters(): void
    {
        $photo = $this->photo();

        IndexPhotoFaces::dispatchSync($photo);
        IndexPhotoFaces::dispatchSync($photo); // ré-indexer remplace, ne double pas

        $photo->refresh();
        $this->assertSame(PhotoStatus::Indexed, $photo->status);
        $this->assertSame(2, $photo->faces_count);
        $this->assertSame(2, $photo->faces()->count());
        $this->assertSame(2, $photo->event->fresh()->faces_count);
    }

    public function test_full_pipeline_in_sync_queue(): void
    {
        $photo = $this->photo();

        ProcessPhoto::dispatchSync($photo);

        $this->assertSame(PhotoStatus::Indexed, $photo->fresh()->status);
        $this->assertSame([$photo->id], $this->faceEngine->indexed);
    }

    public function test_removing_a_photo_cleans_everything(): void
    {
        $photo = $this->photo();
        $photo->event->forceFill(['photos_count' => 1])->save();
        ProcessPhoto::dispatchSync($photo);
        $photo->refresh();

        app(PhotoRemover::class)->remove($photo);

        $this->assertModelMissing($photo);
        Storage::disk(Photo::disk())->assertMissing($photo->original_path);
        Storage::disk(Photo::disk())->assertMissing($photo->preview_path);
        $this->assertSame([$photo->id], $this->faceEngine->deletedPhotos);
        $this->assertSame([0, 0], [$photo->event->fresh()->photos_count, $photo->event->fresh()->faces_count]);
    }

    public function test_purge_removes_face_data_and_marks_event(): void
    {
        $photo = $this->photo();
        IndexPhotoFaces::dispatchSync($photo);

        PurgeEventFaces::dispatchSync($photo->event);

        $event = $photo->event->fresh();
        $this->assertNotNull($event->faces_purged_at);
        $this->assertSame(0, $event->faces()->count());
        $this->assertFalse($event->faceSearchEnabled());
        $this->assertSame([$event->id], $this->faceEngine->deletedEvents);
    }

    public function test_scheduler_purges_only_expired_events(): void
    {
        Queue::fake();
        $expired = Event::factory()->create(['event_date' => now()->subDays(100), 'retention_days' => 30]);
        Event::factory()->create(['event_date' => now()->subDays(10), 'retention_days' => 30]);

        $this->artisan('schedule:test', ['--name' => 'purge-expired-events'])->assertSuccessful();

        Queue::assertPushed(PurgeEventFaces::class, 1);
        Queue::assertPushed(PurgeEventFaces::class, fn ($job) => $job->event->is($expired));
    }
}
