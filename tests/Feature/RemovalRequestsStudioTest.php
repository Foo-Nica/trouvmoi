<?php

namespace Tests\Feature;

use App\Filament\Studio\Resources\RemovalRequests\Pages\ListRemovalRequests;
use App\Models\Event;
use App\Models\Photo;
use App\Models\RemovalRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RemovalRequestsStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_photographer_handles_requests_for_own_events_only(): void
    {
        Filament::setCurrentPanel('studio');
        $mine = Event::factory()->create(['photos_count' => 1]);
        $photo = Photo::factory()->for($mine)->create();
        $request = RemovalRequest::create(['event_id' => $mine->id, 'photo_id' => $photo->id, 'reason' => 'Retirez-la svp']);
        $other = RemovalRequest::create(['event_id' => Event::factory()->create()->id, 'reason' => 'Autre']);

        $this->actingAs($mine->owner);

        Livewire::test(ListRemovalRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->assertCanNotSeeTableRecords([$other])
            ->callTableAction('remove', $request);

        $this->assertModelMissing($photo);
        $this->assertSame('done', $request->fresh()->status);
    }

    public function test_others_cannot_see_requests(): void
    {
        RemovalRequest::create(['event_id' => Event::factory()->create()->id, 'reason' => 'x']);

        $this->actingAs(User::factory()->create())->get('/studio/removal-requests')->assertOk()->assertDontSee('Retirer la photo');
    }
}
