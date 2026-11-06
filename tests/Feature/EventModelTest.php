<?php

namespace Tests\Feature;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_event_gets_short_unique_slug_and_defaults(): void
    {
        $event = Event::create(['title' => 'Test'] + ['user_id' => User::factory()->create()->id]);

        $this->assertMatchesRegularExpression('/^[a-km-z2-9]{8}$/', $event->slug);
        $this->assertSame(AccessMode::Gallery, $event->fresh()->access_mode);
        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_expiry_follows_event_date_and_retention(): void
    {
        $event = Event::factory()->create(['event_date' => '2026-12-01', 'retention_days' => 30]);

        $this->assertSame('2026-12-31', $event->expires_at->toDateString());
    }

    public function test_pin_is_hashed_and_checked(): void
    {
        $event = Event::factory()->create(['access_mode' => AccessMode::Pin]);
        $event->setPin('2468');
        $event->save();

        $this->assertNotSame('2468', $event->pin_hash);
        $this->assertTrue($event->fresh()->checkPin('2468'));
        $this->assertFalse($event->fresh()->checkPin('0000'));
    }

    public function test_minors_disable_face_search(): void
    {
        $this->assertFalse(Event::factory()->create(['has_minors' => true])->faceSearchEnabled());
        $this->assertTrue(Event::factory()->create()->faceSearchEnabled());
    }

    public function test_accessible_scope_includes_members_only(): void
    {
        $owner = User::factory()->create();
        $assistant = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->for($owner, 'owner')->create();
        $event->members()->attach($assistant, ['role' => 'assistant']);

        $this->assertTrue(Event::accessibleBy($assistant)->whereKey($event->id)->exists());
        $this->assertFalse(Event::accessibleBy($stranger)->whereKey($event->id)->exists());
        $this->assertTrue($event->isAccessibleBy($owner));
        $this->assertFalse($event->isAccessibleBy($stranger));
    }
}
