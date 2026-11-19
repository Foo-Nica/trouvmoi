<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Studio\Resources\Events\EventResource;
use App\Filament\Studio\Resources\Events\Widgets\EventStatsOverview;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StudioAndGuestPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_presents_the_brand_and_the_event_code_form(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Chaque image mérite')
            ->assertSee(route('event.find'))
            ->assertDontSee('FCFA');
    }

    public function test_event_code_leads_to_the_event_page(): void
    {
        $event = Event::factory()->create();

        $this->get(route('event.find', ['code' => ' '.strtoupper($event->slug).' ']))
            ->assertRedirect(route('event.show', $event->slug));
    }

    public function test_unknown_event_code_returns_home_with_an_error(): void
    {
        $this->from('/')->get(route('event.find', ['code' => 'zzzz9999']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('code');
    }

    public function test_public_registration_is_closed(): void
    {
        $this->get('/studio/register')->assertNotFound();
    }

    public function test_guest_page_shows_the_brand_instead_of_the_account_name(): void
    {
        $event = Event::factory()->create();
        $event->owner->update(['name' => 'Compte interne']);

        $this->get(route('event.show', $event->slug))->assertOk()
            ->assertSee(config('app.name'))
            ->assertDontSee('Compte interne');
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

    public function test_event_stats_widget(): void
    {
        $event = Event::factory()->create(['photos_count' => 12, 'scans_count' => 40]);
        $this->actingAs($event->owner);

        Livewire::test(EventStatsOverview::class, ['record' => $event])
            ->assertSee('Scans du QR')
            ->assertSee('40')
            ->assertSee('12');
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

    public function test_admin_creates_a_team_member_who_can_sign_in(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Awa',
                'email' => 'awa@trechart.test',
                'role' => UserRole::Photographer->value,
                'password' => 'mot-de-passe-solide',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = User::where('email', 'awa@trechart.test')->sole();
        $this->assertTrue(Hash::check('mot-de-passe-solide', $member->password));
        $this->assertNotNull($member->email_verified_at);
        $this->assertSame('studio', $member->plan);
    }

    public function test_qr_code_download(): void
    {
        $event = Event::factory()->create();

        $png = $this->actingAs($event->owner)->get(route('studio.events.qr', [$event, 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $this->actingAs($event->owner)->get(route('studio.events.qr', [$event, 'svg']))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs(User::factory()->create())->get(route('studio.events.qr', [$event, 'png']))->assertNotFound();
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

    public function test_guest_pages_are_private_and_never_indexed(): void
    {
        $event = Event::factory()->create();

        $this->get(route('event.show', $event->slug))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_legal_pages_name_the_brand_without_plans(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Politique de confidentialité')
            ->assertSee(config('trouvmoi.legal.email'));
        $this->get(route('legal.terms'))->assertOk()
            ->assertSee(config('trouvmoi.legal.company'))
            ->assertDontSee('FCFA');
        $this->get('/')->assertSee(route('legal.privacy'));
    }

    public function test_links_use_https_behind_the_hosting_proxy(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'trouvmoi.up.railway.app'])
            ->get('/')
            ->assertSee('https://trouvmoi.up.railway.app/confidentialite', false);
    }

    public function test_unknown_event_is_404(): void
    {
        $this->get('/e/inconnu1')->assertNotFound();
    }
}
