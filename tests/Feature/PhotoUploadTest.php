<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, Event $event, ?UploadedFile $file = null)
    {
        $file ??= UploadedFile::fake()->createWithContent('IMG_0001.jpg', $this->jpeg());

        return $this->actingAs($user)->postJson(route('studio.events.photos.store', $event), ['file' => $file]);
    }

    public function test_photographer_uploads_a_photo(): void
    {
        Queue::fake();
        $event = Event::factory()->create();

        $this->upload($event->owner, $event)->assertCreated()->assertJson(['duplicate' => false, 'status' => 'received']);

        $photo = Photo::sole();
        Storage::disk(Photo::disk())->assertExists($photo->original_path);
        $this->assertSame(1, $event->fresh()->photos_count);
        $this->assertSame('IMG_0001.jpg', $photo->original_name);
        Queue::assertPushed(ProcessPhoto::class);
    }

    public function test_same_photo_sent_twice_is_not_duplicated(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        $bytes = $this->jpeg();

        $this->upload($event->owner, $event, UploadedFile::fake()->createWithContent('a.jpg', $bytes))->assertCreated();
        $this->upload($event->owner, $event, UploadedFile::fake()->createWithContent('a.jpg', $bytes))
            ->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, Photo::count());
        $this->assertSame(1, $event->fresh()->photos_count);
    }

    public function test_assistant_can_upload_but_stranger_cannot(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        $assistant = User::factory()->create();
        $event->members()->attach($assistant, ['role' => 'assistant']);

        $this->upload($assistant, $event)->assertCreated();
        $this->upload(User::factory()->create(), $event)->assertForbidden();
    }

    public function test_rejects_non_images_and_guests(): void
    {
        $event = Event::factory()->create();

        $this->upload($event->owner, $event, UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_guests_cannot_upload(): void
    {
        $event = Event::factory()->create();

        $this->postJson(route('studio.events.photos.store', $event))->assertUnauthorized();
    }

    public function test_plan_photo_limit(): void
    {
        Queue::fake();
        config(['trouvmoi.plans.trial.photos_per_event' => 1]);
        $event = Event::factory()->for(User::factory()->plan('trial'), 'owner')->create();

        $this->upload($event->owner, $event)->assertCreated();
        $this->upload($event->owner, $event, UploadedFile::fake()->createWithContent('b.jpg', $this->jpeg(seed: 2)))
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'Limite'));
    }

    public function test_unverified_photographer_cannot_upload(): void
    {
        $user = User::factory()->unverified()->create();
        $event = Event::factory()->for($user, 'owner')->create();

        $this->upload($user, $event)->assertForbidden();
    }
}
