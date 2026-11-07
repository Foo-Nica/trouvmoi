<?php

namespace Tests\Feature;

use App\Enums\AccessMode;
use App\Enums\PhotoStatus;
use App\Filament\Studio\Resources\Events\Pages\CreateEvent;
use App\Filament\Studio\Resources\Events\Pages\EditEvent;
use App\Filament\Studio\Resources\Events\Pages\ManageEventPhotos;
use App\Jobs\IndexPhotoFaces;
use App\Jobs\ProcessPhoto;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StudioEventFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('studio');
    }

    public function test_photographer_creates_an_event_with_pin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => 'Mariage de Awa & Koffi',
                'event_date' => '2026-12-12',
                'location' => 'Cotonou',
                'access_mode' => AccessMode::Pin->value,
                'pin' => '4826',
                'retention_days' => 90,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertTrue($event->checkPin('4826'));
        $this->assertSame('2027-03-12', $event->expires_at->toDateString());
    }

    public function test_pin_is_required_when_pin_mode_is_chosen(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Gala', 'access_mode' => AccessMode::Pin->value])
            ->call('create')
            ->assertHasFormErrors(['pin' => 'required']);
    }

    public function test_monthly_event_limit_of_the_plan(): void
    {
        config(['trouvmoi.plans.trial.events_per_month' => 1]);
        $user = User::factory()->plan('trial')->create();
        Event::factory()->for($user, 'owner')->create();
        $this->actingAs($user);

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Deuxième événement', 'access_mode' => AccessMode::Gallery->value])
            ->call('create');

        $this->assertSame(1, Event::count());
    }

    public function test_turning_watermark_off_redoes_published_images_without_reindexing(): void
    {
        Queue::fake();
        $event = Event::factory()->create(['watermark_enabled' => true]);
        $published = Photo::factory()->for($event)->create(['thumb_path' => 't.webp', 'status' => PhotoStatus::Indexed]);
        Photo::factory()->for($event)->create(['thumb_path' => null]);
        $this->actingAs($event->owner);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['watermark_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Queue::assertPushed(ProcessPhoto::class, 1);
        Queue::assertPushed(ProcessPhoto::class, fn (ProcessPhoto $job) => $job->photo->is($published) && ! $job->indexFaces);
    }

    public function test_saving_without_watermark_change_redoes_nothing(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        Photo::factory()->for($event)->create(['thumb_path' => 't.webp']);
        $this->actingAs($event->owner);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['title' => 'Nouveau titre'])
            ->call('save');

        Queue::assertNotPushed(ProcessPhoto::class);
    }

    public function test_relaunching_face_recognition_queues_every_published_photo(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        Photo::factory()->for($event)->count(2)->create(['thumb_path' => 't.webp']);
        $this->actingAs($event->owner);

        Livewire::test(ManageEventPhotos::class, ['record' => $event->getRouteKey()])
            ->callAction('reindex');

        Queue::assertPushed(IndexPhotoFaces::class, 2);
    }

    public function test_switching_away_from_pin_mode_clears_the_pin(): void
    {
        $event = Event::factory()->create(['access_mode' => AccessMode::Pin]);
        $event->setPin('1234');
        $event->save();
        $this->actingAs($event->owner);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['access_mode' => AccessMode::Gallery->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($event->fresh()->pin_hash);
    }
}
