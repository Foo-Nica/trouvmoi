<?php

namespace Tests\Feature;

use App\Filament\Studio\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioAndGuestPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Je suis photographe');
    }

    public function test_studio_pages_load_for_photographer(): void
    {
        $event = Event::factory()->create();
        $this->actingAs($event->owner);

        $this->get(EventResource::getUrl('index'))->assertOk()->assertSee($event->title);
        $this->get(EventResource::getUrl('create'))->assertOk();
        $this->get(EventResource::getUrl('edit', ['record' => $event]))->assertOk();
        $this->get(EventResource::getUrl('photos', ['record' => $event]))->assertOk()->assertSee('Envoyer des photos');
    }

    public function test_photographer_cannot_open_someone_elses_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(EventResource::getUrl('photos', ['record' => $event]))
            ->assertNotFound();
    }

    public function test_admin_panel_is_for_admins_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $admin = User::factory()->admin()->create();
        Event::factory()->create(['title' => 'Gala annuel']);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/events')->assertOk()->assertSee('Gala annuel');
    }

    public function test_qr_code_download(): void
    {
        $event = Event::factory()->create();

        $png = $this->actingAs($event->owner)->get(route('studio.events.qr', [$event, 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $this->actingAs($event->owner)->get(route('studio.events.qr', [$event, 'svg']))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs(User::factory()->create())->get(route('studio.events.qr', [$event, 'png']))->assertForbidden();
    }

    public function test_guest_page_counts_scans_when_online(): void
    {
        $event = Event::factory()->create();

        $this->get(route('event.show', $event->slug))->assertOk()->assertSee($event->title);

        $this->assertSame(1, $event->fresh()->scans_count);
    }

    public function test_draft_event_is_hidden_from_guests_but_previewable(): void
    {
        $event = Event::factory()->draft()->create();

        $this->get(route('event.show', $event->slug))->assertOk()->assertSee('pas encore en ligne');
        $this->actingAs($event->owner)->get(route('event.show', $event->slug))->assertOk()->assertDontSee('pas encore en ligne');
        $this->assertSame(0, $event->fresh()->scans_count);
    }

    public function test_unknown_event_is_404(): void
    {
        $this->get('/e/inconnu1')->assertNotFound();
    }
}
