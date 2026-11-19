<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PhotoStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Notifications\OrderPaid;
use App\Services\Guests\GuestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PhotoSalesTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER = [
        'customer_name' => 'Awa Mensah',
        'customer_phone' => '01 97 00 00 00',
        'customer_email' => 'awa@example.test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** Photo publiée avec toutes ses versions sur le disque. */
    private function photo(Event $event, array $attributes = []): Photo
    {
        $photo = Photo::factory()->for($event)->create($attributes + ['status' => PhotoStatus::Processed]);
        $paths = [
            'thumb_path' => "t/{$photo->id}.webp",
            'preview_path' => "p/{$photo->id}.webp",
            'download_path' => "d/{$photo->id}.jpg",
            'hd_path' => "hd/{$photo->id}.jpg",
        ];
        foreach ($paths as $column => $path) {
            Storage::disk(Photo::disk())->put($path, $column === 'hd_path' ? 'ORIGINAL' : 'WATERMARKED');
        }
        $photo->update($paths);

        return $photo;
    }

    /** Garde le cookie invité posé par la réponse, comme un navigateur. */
    private function keepGuestCookie(TestResponse $response): TestResponse
    {
        if ($cookie = $response->getCookie(GuestSession::COOKIE)) {
            $this->withCookie(GuestSession::COOKIE, $cookie->getValue());
        }

        return $response;
    }

    private function checkout(Event $event, array $extra = []): Order
    {
        $this->keepGuestCookie($this->post(route('order.store', $event->slug), self::CUSTOMER + $extra));

        return Order::latest()->firstOrFail();
    }

    public function test_public_event_gives_the_logo_version_free_and_sells_the_original(): void
    {
        $event = Event::factory()->create(['original_price' => 1500]);
        $photo = $this->photo($event);

        $this->get(route('event.photo', [$event->slug, $photo->id]))->assertOk()
            ->assertSee('1 500 FCFA')
            ->assertSee(route('event.download', [$event->slug, $photo->id]));

        $this->get(route('event.download', [$event->slug, $photo->id]))->assertOk()->assertStreamedContent('WATERMARKED');
    }

    public function test_personality_event_has_no_free_download(): void
    {
        $event = Event::factory()->client(originalPrice: 3000)->create();
        $photo = $this->photo($event);

        $this->get(route('event.photo', [$event->slug, $photo->id]))->assertOk()
            ->assertSee('3 000 FCFA')
            ->assertDontSee(route('event.download', [$event->slug, $photo->id]));

        $this->get(route('event.download', [$event->slug, $photo->id]))->assertNotFound();
        $this->get(route('event.original', [$event->slug, $photo->id]))->assertNotFound();
    }

    public function test_personality_event_without_price_offers_the_original(): void
    {
        $event = Event::factory()->client(originalPrice: 0)->create();
        $photo = $this->photo($event);

        $this->get(route('event.original', [$event->slug, $photo->id]))->assertOk()->assertStreamedContent('ORIGINAL');
    }

    public function test_cart_checkout_and_simulated_payment_unlock_the_originals(): void
    {
        Notification::fake();
        $event = Event::factory()->create(['original_price' => 1500]);
        [$first, $second, $notBought] = [$this->photo($event), $this->photo($event, ['price' => 2500]), $this->photo($event)];

        $this->post(route('cart.add', [$event->slug, $first->id]))->assertRedirect();
        $this->post(route('cart.add', [$event->slug, $second->id]))->assertRedirect();
        $this->get(route('order.checkout', $event->slug))->assertOk()->assertSee('4 000 FCFA');

        $order = $this->checkout($event);
        $this->assertSame(4000, $order->amount);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $order->photos()->pluck('photos.id')->all());

        $this->get(route('order.download', [$order->access_token, $first->id]))->assertForbidden();

        $this->post(route('order.simulate.submit', $order->access_token), ['result' => 'paid'])
            ->assertRedirect(route('order.return', ['token' => $order->access_token, 'simulation' => 'paid']));
        $this->get(route('order.return', ['token' => $order->access_token, 'simulation' => 'paid']))
            ->assertRedirect(route('order.show', $order->access_token));

        $this->assertTrue($order->fresh()->isPaid());
        $this->get(route('order.download', [$order->access_token, $first->id]))->assertOk()->assertStreamedContent('ORIGINAL');
        $this->get(route('order.download', [$order->access_token, $notBought->id]))->assertNotFound();
        $this->get(route('event.photo', [$event->slug, $first->id]))->assertSee(route('order.download', [$order->access_token, $first->id]));
        Notification::assertSentOnDemand(OrderPaid::class);
    }

    public function test_pack_covers_every_photo_including_later_ones(): void
    {
        $event = Event::factory()->client(originalPrice: 2000, packPrice: 15000)->create();
        $this->photo($event);
        $this->photo($event);

        $order = $this->checkout($event, ['pack' => 1]);
        $this->assertSame(15000, $order->amount);
        $this->assertTrue($order->is_pack);

        $this->get(route('order.return', ['token' => $order->access_token, 'simulation' => 'paid']));
        $later = $this->photo($event);

        $this->get(route('order.show', $order->access_token))->assertOk()
            ->assertSee(route('order.download', [$order->access_token, $later->id]));
    }

    public function test_failed_payment_keeps_the_photos_locked(): void
    {
        $event = Event::factory()->client(originalPrice: 2000)->create();
        $photo = $this->photo($event);
        $this->post(route('cart.add', [$event->slug, $photo->id]));
        $order = $this->checkout($event);

        $this->get(route('order.return', ['token' => $order->access_token, 'simulation' => 'failed']));

        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
        $this->get(route('order.download', [$order->access_token, $photo->id]))->assertForbidden();
    }

    public function test_checkout_requires_a_name_and_a_phone(): void
    {
        $event = Event::factory()->client()->create();
        $photo = $this->photo($event);
        $this->post(route('cart.add', [$event->slug, $photo->id]));

        $this->post(route('order.store', $event->slug), [])
            ->assertSessionHasErrors(['customer_name', 'customer_phone']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_free_photos_cannot_be_added_to_the_cart(): void
    {
        $event = Event::factory()->create(['original_price' => null]);
        $photo = $this->photo($event);

        $this->post(route('cart.add', [$event->slug, $photo->id]))->assertNotFound();
    }

    public function test_simulation_never_pays_in_production(): void
    {
        $event = Event::factory()->client()->create();
        $photo = $this->photo($event);
        $this->post(route('cart.add', [$event->slug, $photo->id]));
        $order = $this->checkout($event);

        $this->app->detectEnvironment(fn () => 'production');

        $this->get(route('order.simulate', $order->access_token))->assertNotFound();
        $this->get(route('order.return', ['token' => $order->access_token, 'simulation' => 'paid']));
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_fedapay_redirects_to_its_payment_page_and_confirms_with_the_api(): void
    {
        config(['trouvmoi.payments.driver' => 'fedapay', 'trouvmoi.payments.fedapay.secret_key' => 'sk_sandbox_test']);
        Http::fake([
            'sandbox-api.fedapay.com/v1/transactions' => Http::response(['v1/transaction' => ['id' => 4242, 'status' => 'pending']], 201),
            'sandbox-api.fedapay.com/v1/transactions/4242/token' => Http::response(['token' => 'tok', 'url' => 'https://process.fedapay.com/tok']),
            'sandbox-api.fedapay.com/v1/transactions/4242' => Http::response(['v1/transaction' => ['id' => 4242, 'status' => 'approved', 'amount' => 2000]]),
        ]);
        $event = Event::factory()->client(originalPrice: 2000)->create();
        $photo = $this->photo($event);
        $this->post(route('cart.add', [$event->slug, $photo->id]));

        $response = $this->post(route('order.store', $event->slug), self::CUSTOMER);

        $response->assertRedirect('https://process.fedapay.com/tok');
        $order = Order::sole();
        $this->assertSame('4242', $order->gateway_reference);
        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox-api.fedapay.com/v1/transactions'
            && $request['amount'] === 2000
            && $request['currency'] === ['iso' => 'XOF']
            && $request->hasHeader('Authorization', 'Bearer sk_sandbox_test'));

        $this->get(route('order.return', $order->access_token))->assertRedirect(route('order.show', $order->access_token));
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_fedapay_amount_lower_than_the_order_is_not_accepted(): void
    {
        config(['trouvmoi.payments.fedapay.secret_key' => 'sk_sandbox_test']);
        Http::fake([
            'sandbox-api.fedapay.com/v1/transactions/77' => Http::response(['v1/transaction' => ['id' => 77, 'status' => 'approved', 'amount' => 100]]),
        ]);
        $order = Order::factory()->create(['gateway' => 'fedapay', 'gateway_reference' => '77', 'amount' => 2000]);

        $this->get(route('order.return', $order->access_token));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_fedapay_webhook_with_a_bad_signature_is_ignored(): void
    {
        config(['trouvmoi.payments.fedapay.secret_key' => 'sk', 'trouvmoi.payments.fedapay.webhook_secret' => 'whsec']);
        $order = Order::factory()->create(['gateway' => 'fedapay', 'gateway_reference' => '55']);

        $this->postJson(route('payment.webhook', 'fedapay'), ['name' => 'transaction.approved', 'entity' => ['id' => 55]], [
            'X-FEDAPAY-SIGNATURE' => 't='.time().',s=forged',
        ])->assertStatus(202);

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_fedapay_webhook_with_a_valid_signature_confirms_the_payment(): void
    {
        config(['trouvmoi.payments.fedapay.secret_key' => 'sk', 'trouvmoi.payments.fedapay.webhook_secret' => 'whsec']);
        Http::fake([
            'sandbox-api.fedapay.com/v1/transactions/55' => Http::response(['v1/transaction' => ['id' => 55, 'status' => 'approved', 'amount' => 2000]]),
        ]);
        $order = Order::factory()->create(['gateway' => 'fedapay', 'gateway_reference' => '55', 'amount' => 2000]);
        $payload = json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 55]]);
        $timestamp = (string) time();

        $this->call('POST', route('payment.webhook', 'fedapay'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => "t={$timestamp},s=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec'),
        ], content: $payload)->assertOk();

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_kkiapay_transaction_is_verified_with_the_private_keys(): void
    {
        config(['trouvmoi.payments.kkiapay' => array_merge(config('trouvmoi.payments.kkiapay'), [
            'public_key' => 'pk', 'private_key' => 'tpk', 'secret' => 'tsk', 'sandbox' => true,
        ])]);
        Http::fake([
            'api-sandbox.kkiapay.me/api/v1/transactions/status' => Http::response(['status' => 'SUCCESS', 'amount' => 2000, 'state' => null]),
        ]);
        $order = Order::factory()->create(['gateway' => 'kkiapay', 'amount' => 2000]);

        $this->get(route('order.return', ['token' => $order->access_token, 'transaction_id' => 'kk-1']));

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame('kk-1', $order->fresh()->gateway_reference);
        Http::assertSent(fn ($request) => $request['transactionId'] === 'kk-1' && $request->hasHeader('X-PRIVATE-KEY', 'tpk'));
    }

    public function test_a_kkiapay_transaction_cannot_pay_two_orders(): void
    {
        $paid = Order::factory()->paid()->create(['gateway' => 'kkiapay', 'gateway_reference' => 'kk-1']);
        $other = Order::factory()->create(['gateway' => 'kkiapay', 'event_id' => $paid->event_id]);

        $this->get(route('order.return', ['token' => $other->access_token, 'transaction_id' => 'kk-1']));

        $this->assertSame(OrderStatus::Failed, $other->fresh()->status);
    }

    public function test_payment_provider_outage_leaves_a_retryable_order(): void
    {
        config(['trouvmoi.payments.driver' => 'fedapay', 'trouvmoi.payments.fedapay.secret_key' => 'sk']);
        Http::fake(['sandbox-api.fedapay.com/v1/transactions' => Http::response(['message' => 'Service indisponible'], 503)]);
        $event = Event::factory()->client()->create();
        $photo = $this->photo($event);
        $this->post(route('cart.add', [$event->slug, $photo->id]));

        $order = $this->checkout($event);

        $this->assertSame(OrderStatus::Failed, $order->status);
        $this->get(route('order.show', $order->access_token))->assertOk()->assertSee(route('order.retry', $order->access_token));
    }

    public function test_order_page_is_only_reachable_with_its_private_link(): void
    {
        $this->get(route('order.show', str_repeat('x', 48)))->assertNotFound();
    }
}
