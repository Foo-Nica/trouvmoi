<?php

namespace Tests\Feature;

use App\Enums\AccessMode;
use App\Enums\PhotoStatus;
use App\Models\ConsentLog;
use App\Models\Download;
use App\Models\Event;
use App\Models\Face;
use App\Models\FaceSearch;
use App\Models\Folder;
use App\Models\Photo;
use App\Models\RemovalRequest;
use App\Notifications\RemovalRequested;
use App\Services\FaceEngine\FaceEngine;
use App\Services\FaceEngine\FaceEngineException;
use App\Services\FaceEngine\FaceMatch;
use App\Services\Guests\GuestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeFaceEngine;
use Tests\TestCase;

class GuestJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    /** @var list<Photo> */
    private array $photos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();

        foreach (range(1, 4) as $i) {
            $photo = Photo::factory()->for($this->event)->create([
                'status' => PhotoStatus::Indexed,
                'thumb_path' => "t/{$i}.webp",
                'preview_path' => "p/{$i}.webp",
                'download_path' => "d/{$i}.jpg",
                'width' => 1600,
                'height' => 1000,
            ]);
            Storage::disk(Photo::disk())->put("d/{$i}.jpg", $this->jpeg(seed: $i));
            Face::create(['photo_id' => $photo->id, 'event_id' => $this->event->id, 'external_id' => "face{$i}",
                'x' => 400, 'y' => 200, 'w' => 160, 'h' => 200]);
            $this->photos[] = $photo;
        }

        $this->faceEngine->matches = [
            new FaceMatch($this->photos[0]->id, 'face1', 0.82, true),
            new FaceMatch($this->photos[1]->id, 'face2', 0.61, true),
            new FaceMatch($this->photos[2]->id, 'face3', 0.39, false),
        ];
    }

    /** Donne son consentement et garde le cookie invité, comme le ferait le navigateur. */
    private function consent(?Event $event = null): TestResponse
    {
        $response = $this->post(route('event.consent', ($event ?? $this->event)->slug), ['accept' => '1']);

        if ($cookie = $response->getCookie(GuestSession::COOKIE)) {
            $this->withCookie(GuestSession::COOKIE, $cookie->getValue());
        }

        return $response;
    }

    /** Oublie le cookie invité : une autre personne ouvre le lien. */
    private function asAnotherPerson(): void
    {
        $this->defaultCookies = [];
        $this->flushSession();
    }

    /** Lance une recherche par visage et renvoie l'URL des résultats. */
    private function searchFace(string $faceId = 'face1'): string
    {
        $response = $this->post(route('event.search.face', $this->event->slug), ['face_id' => $faceId]);
        $response->assertRedirectContains('/r/');

        return $response->headers->get('Location');
    }

    public function test_landing_shows_gallery_and_consent(): void
    {
        $this->get(route('event.show', $this->event->slug))
            ->assertOk()
            ->assertSee($this->event->title)
            ->assertSee('Avant de commencer')
            ->assertSee('Toutes les photos');
    }

    public function test_gallery_pages_as_json(): void
    {
        $this->getJson(route('event.photos', $this->event->slug))
            ->assertOk()->assertJsonCount(4, 'photos')->assertJsonPath('next', null)->assertJsonPath('total', 4);
    }

    public function test_consent_is_required_and_logged(): void
    {
        $this->post(route('event.consent', $this->event->slug))->assertSessionHasErrors('accept');

        $this->consent()->assertRedirect()->assertCookie(GuestSession::COOKIE);

        $this->assertSame(1, ConsentLog::count());
        $this->assertSame(config('trouvmoi.consent_notice_version'), ConsentLog::first()->notice_version);
    }

    public function test_search_without_consent_asks_for_it(): void
    {
        $this->post(route('event.search.face', $this->event->slug), ['face_id' => 'face1'])
            ->assertRedirect(route('event.photo', [$this->event->slug, $this->photos[0]->id]));

        $this->assertSame([], $this->faceEngine->searches);
    }

    public function test_photo_viewer_shows_tappable_faces_after_consent(): void
    {
        $this->consent();

        $this->get(route('event.photo', [$this->event->slug, $this->photos[0]->id]))
            ->assertOk()
            ->assertSee('value="face1"', false)
            ->assertSee('left: 25%', false);
    }

    public function test_full_gallery_journey_to_folder_and_zip(): void
    {
        $this->consent();
        $results = $this->searchFace();

        $this->get($results)->assertOk()
            ->assertSee('Vos photos')
            ->assertSee('Peut-être vous');
        $this->assertSame(1, FaceSearch::where('mode', 'gallery')->count());

        $searchId = Str::afterLast($results, '/');
        $response = $this->post(route('folder.store', $this->event->slug), [
            'search' => $searchId,
            'photos' => [$this->photos[0]->id, $this->photos[1]->id], // « Ce n'est pas moi » sur la 3e
        ]);

        $folder = Folder::sole();
        $response->assertRedirect(route('folder.show', $folder->access_token));
        $this->assertSame(2, $folder->keptPhotos()->count());
        $this->assertSame(['face1', 'face2'], $folder->query_face_ids);

        $this->get(route('folder.show', $folder->access_token))->assertOk()->assertSee('Tout télécharger');

        $zip = $this->get(route('folder.zip', $folder->access_token));
        $zip->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertStringStartsWith('PK', $zip->streamedContent());
        $this->assertSame(2, Download::where('type', 'zip')->value('photos_count'));
    }

    public function test_selfie_journey(): void
    {
        $this->event->update(['access_mode' => AccessMode::Selfie]);
        $this->consent();

        $this->get(route('event.show', $this->event->slug))->assertOk()->assertDontSee('Toutes les photos');
        $this->getJson(route('event.photos', $this->event->slug))->assertNotFound();

        $this->post(route('event.search.selfie', $this->event->slug), [
            'selfie' => UploadedFile::fake()->createWithContent('selfie.jpg', $this->jpeg()),
        ])->assertRedirectContains('/r/');

        $this->assertSame('selfie', $this->faceEngine->searches[0][0]);
        $this->assertSame(1, FaceSearch::where('mode', 'selfie')->count());
        // Le selfie n'est enregistré nulle part.
        $this->assertSame([], Storage::disk(Photo::disk())->allFiles('selfies'));
    }

    public function test_selfie_without_face_gives_advice(): void
    {
        $this->faceEngine->selfieHasFace = false;
        $this->consent();

        $this->post(route('event.search.selfie', $this->event->slug), [
            'selfie' => UploadedFile::fake()->createWithContent('selfie.jpg', $this->jpeg()),
        ])->assertSessionHasErrors('selfie');
    }

    public function test_engine_outage_is_handled_gracefully(): void
    {
        $this->app->instance(FaceEngine::class, new class extends FakeFaceEngine
        {
            public function searchByFaces($event, array $faceIds, ?int $limit = null): array
            {
                throw new FaceEngineException('down');
            }
        });
        $this->consent();

        $this->post(route('event.search.face', $this->event->slug), ['face_id' => 'face1'])
            ->assertSessionHasErrors('search');
    }

    public function test_results_belong_to_the_guest_who_searched(): void
    {
        $this->consent();
        $results = $this->searchFace();

        $this->asAnotherPerson();

        $this->get($results)->assertRedirect(route('event.show', $this->event->slug));
    }

    public function test_folder_refreshes_with_new_photos_but_not_excluded_ones(): void
    {
        $this->consent();
        $searchId = Str::afterLast($this->searchFace(), '/');
        $this->post(route('folder.store', $this->event->slug), ['search' => $searchId, 'photos' => [$this->photos[0]->id]]);
        $folder = Folder::sole();
        $this->get(route('folder.show', $folder->access_token))->assertSee('Dossier créé');
        $folder->forceFill(['refreshed_at' => now()->subHour()])->save();

        // Une nouvelle photo de l'invité est indexée après la création du dossier.
        $this->faceEngine->matches[] = new FaceMatch($this->photos[3]->id, 'face4', 0.7, true);
        $this->photos[3]->touch();

        $this->get(route('folder.show', $folder->access_token))->assertOk()->assertSee('nouvelle photo ajoutée');

        $kept = $folder->keptPhotos()->pluck('photos.id')->all();
        $this->assertContains($this->photos[3]->id, $kept);
        $this->assertNotContains($this->photos[1]->id, $kept); // décochée : ne revient pas
    }

    public function test_only_folder_owner_can_edit_but_anyone_with_link_can_download(): void
    {
        $this->consent();
        $searchId = Str::afterLast($this->searchFace(), '/');
        $this->post(route('folder.store', $this->event->slug), ['search' => $searchId, 'photos' => [$this->photos[0]->id, $this->photos[1]->id]]);
        $folder = Folder::sole();

        $this->post(route('folder.exclude', [$folder->access_token, $this->photos[1]->id]))->assertRedirect();
        $this->assertSame(1, $folder->keptPhotos()->count());

        $this->asAnotherPerson();

        $this->get(route('folder.download', [$folder->access_token, $this->photos[0]->id]))->assertOk()->assertDownload();
        $this->post(route('folder.exclude', [$folder->access_token, $this->photos[0]->id]))->assertForbidden();
        $this->delete(route('folder.destroy', $folder->access_token))->assertForbidden();
    }

    public function test_pin_protected_event(): void
    {
        $this->event->update(['access_mode' => AccessMode::Pin]);
        $this->event->setPin('2580');
        $this->event->save();

        $this->get(route('event.show', $this->event->slug))->assertOk()->assertSee('Code d\'accès');
        $this->getJson(route('event.photos', $this->event->slug))->assertForbidden();

        $this->post(route('event.pin', $this->event->slug), ['pin' => '0000'])->assertSessionHasErrors('pin');
        $this->post(route('event.pin', $this->event->slug), ['pin' => '2580'])->assertRedirect(route('event.show', $this->event->slug));

        $this->getJson(route('event.photos', $this->event->slug))->assertOk();
    }

    public function test_minors_event_has_gallery_but_no_face_search(): void
    {
        $this->event->update(['has_minors' => true]);

        $this->get(route('event.show', $this->event->slug))->assertOk()
            ->assertSee('Toutes les photos')->assertDontSee('Retrouver mes photos');
        $this->post(route('event.consent', $this->event->slug), ['accept' => '1'])->assertNotFound();
        $this->get(route('event.download', [$this->event->slug, $this->photos[0]->id]))->assertOk()->assertDownload();
    }

    public function test_hidden_photos_and_other_events_are_not_reachable(): void
    {
        $this->photos[0]->update(['is_visible' => false]);
        $other = Photo::factory()->create(['thumb_path' => 'x.webp']);

        $this->get(route('event.photo', [$this->event->slug, $this->photos[0]->id]))->assertNotFound();
        $this->get(route('event.photo', [$this->event->slug, $other->id]))->assertNotFound();
        $this->getJson(route('event.photos', $this->event->slug))->assertJsonPath('total', 3);
    }

    public function test_removal_request_notifies_the_photographer_once(): void
    {
        Notification::fake();

        $this->post(route('event.removal', [$this->event->slug, $this->photos[2]->id]), ['reason' => 'Je ne veux pas'])
            ->assertRedirect()->assertSessionHas('status');
        $this->post(route('event.removal', [$this->event->slug, $this->photos[2]->id]), ['reason' => 'Je ne veux pas']);

        $this->assertSame('pending', RemovalRequest::sole()->status);
        Notification::assertSentToTimes($this->event->owner, RemovalRequested::class, 1);
    }

    public function test_language_switch(): void
    {
        $this->get(route('event.show', [$this->event->slug, 'lang' => 'en']))->assertOk()->assertSee('Before you start');
        $this->get(route('event.show', $this->event->slug))->assertSee('All photos'); // gardé en session
    }

    public function test_expired_event_folder_is_closed(): void
    {
        $this->consent();
        $searchId = Str::afterLast($this->searchFace(), '/');
        $this->post(route('folder.store', $this->event->slug), ['search' => $searchId, 'photos' => [$this->photos[0]->id]]);
        $folder = Folder::sole();
        $this->event->forceFill(['expires_at' => now()->subDay()])->saveQuietly();

        $this->get(route('folder.show', $folder->access_token))->assertOk()->assertSee('Cet événement est terminé');
        $this->get(route('folder.zip', $folder->access_token))->assertStatus(410);
    }
}
