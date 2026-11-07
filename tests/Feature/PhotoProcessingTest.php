<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PhotoStatus;
use App\Jobs\DeleteEventFiles;
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
            $this->assertNotNull($path);
            Storage::disk(Photo::disk())->assertExists($path);
        }
        [$w, $h] = getimagesizefromstring(Storage::disk(Photo::disk())->get($photo->thumb_path));
        $this->assertSame(400, max($w, $h));
        Queue::assertPushed(IndexPhotoFaces::class);
    }

    public function test_redoing_images_keeps_the_indexed_status_and_skips_indexing(): void
    {
        Queue::fake([IndexPhotoFaces::class]);
        $photo = $this->photo(['watermark_enabled' => false]);
        $photo->update(['status' => PhotoStatus::Indexed, 'faces_count' => 2]);

        ProcessPhoto::dispatchSync($photo, false);

        $this->assertSame(PhotoStatus::Indexed, $photo->fresh()->status);
        $this->assertSame(2, $photo->fresh()->faces_count);
        Queue::assertNotPushed(IndexPhotoFaces::class);
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

    public function test_retention_purges_faces_at_expiry_then_photos_after_grace_period(): void
    {
        Queue::fake();
        config(['trouvmoi.photos_grace_days' => 30]);
        $active = Event::factory()->create(['event_date' => now()->subDays(10), 'retention_days' => 30]);
        $justExpired = Event::factory()->create(['event_date' => now()->subDays(40), 'retention_days' => 30]);
        $longExpired = Event::factory()->create(['event_date' => now()->subDays(100), 'retention_days' => 30]);
        $longExpired->forceFill(['faces_purged_at' => now()->subDays(60), 'photos_count' => 1])->save();
        $oldPhoto = Photo::factory()->for($longExpired)->create();

        $this->artisan('events:purge-expired')->assertSuccessful();

        Queue::assertPushed(PurgeEventFaces::class, 1);
        Queue::assertPushed(PurgeEventFaces::class, fn ($job) => $job->event->is($justExpired));
        $this->assertSame(EventStatus::Archived, $justExpired->fresh()->status);
        $this->assertSame(EventStatus::Published, $active->fresh()->status);

        $this->assertModelMissing($oldPhoto);
        $this->assertSame(0, $longExpired->fresh()->photos_count);
        Queue::assertPushed(DeleteEventFiles::class, fn ($job) => $job->eventId === $longExpired->id);
    }

    public function test_retention_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('events:purge-expired')->assertSuccessful();
    }

    public function test_deleting_an_event_removes_its_files(): void
    {
        $photo = $this->photo();
        $event = $photo->event;

        $event->delete();
        DeleteEventFiles::dispatchSync($event->id);

        Storage::disk(Photo::disk())->assertMissing($photo->original_path);
    }
}
