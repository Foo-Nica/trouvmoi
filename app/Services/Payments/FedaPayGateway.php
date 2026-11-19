<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FedaPay : création d'une transaction, puis redirection vers la page de paiement FedaPay.
 * Au retour (et via le webhook), le statut est relu auprès de l'API avec la clé secrète.
 */
class FedaPayGateway implements PaymentGateway
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'fedapay';
    }

    public function start(Order $order): RedirectResponse|View
    {
        [$firstname, $lastname] = $this->splitName($order->customer_name);
        $customer = array_filter([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $order->customer_email,
            'phone_number' => $this->phone($order->customer_phone),
        ]);

        $transaction = $this->transactionData($this->client()->post('/transactions', [
            'description' => 'Photos TRECHART EMPIRE - '.$order->reference,
            'amount' => $order->amount,
            'currency' => ['iso' => $order->currency],
            'callback_url' => route('order.return', $order->access_token),
            'customer' => $customer,
            'custom_metadata' => ['order_reference' => $order->reference],
        ])->throw()->json());

        $id = (string) ($transaction['id'] ?? throw new RuntimeException('FedaPay : identifiant de transaction absent.'));
        $order->forceFill(['gateway_reference' => $id])->save();

        $url = $this->client()->post("/transactions/{$id}/token")->throw()->json('url');

        return redirect()->away($url ?: throw new RuntimeException('FedaPay : lien de paiement absent.'));
    }

    public function verify(Order $order, Request $request): PaymentResult
    {
        $id = $order->gateway_reference;
        if (! $id) {
            return PaymentResult::pending();
        }

        $transaction = $this->transactionData($this->client()->get("/transactions/{$id}")->throw()->json());
        $status = $transaction['status'] ?? null;

        if ($status === 'approved' && (int) ($transaction['amount'] ?? 0) >= $order->amount) {
            return PaymentResult::paid($id);
        }

        return in_array($status, ['declined', 'canceled', 'refunded', 'expired'], true)
            ? PaymentResult::failed($id)
            : PaymentResult::pending($id);
    }

    public function orderFromWebhook(Request $request): ?Order
    {
        if (! $this->validSignature($request)) {
            return null;
        }

        $id = data_get($request->json()->all(), 'entity.id');

        return $id ? Order::where('gateway', $this->name())->where('gateway_reference', (string) $id)->first() : null;
    }

    /**
     * En-tête X-FEDAPAY-SIGNATURE « t=horodatage,s=signature » : HMAC SHA-256 de « horodatage.corps ».
     * Sans secret configuré, la notification est acceptée : le statut est de toute façon relu via l'API.
     */
    private function validSignature(Request $request): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;
        if (! $secret) {
            return true;
        }

        parse_str(str_replace(',', '&', (string) $request->header('X-FEDAPAY-SIGNATURE')), $parts);
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['s'] ?? null;

        if (! $timestamp || ! $signature || abs(time() - (int) $timestamp) > 600) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), $signature);
    }

    /** @return array<string, mixed> */
    private function transactionData(?array $json): array
    {
        return $json['v1/transaction'] ?? $json['transaction'] ?? $json ?? [];
    }

    private function client(): PendingRequest
    {
        $secret = $this->config['secret_key'] ?? null;
        if (! $secret) {
            throw new RuntimeException('FedaPay : FEDAPAY_SECRET_KEY manquante dans le fichier .env.');
        }

        $base = ($this->config['environment'] ?? 'sandbox') === 'live' ? $this->config['api_url'] : $this->config['sandbox_api_url'];

        return Http::baseUrl($base)->withToken($secret)->acceptJson()->timeout(20);
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? $parts[0] ?? $name];
    }

    /** @return array{number: string, country: string}|null */
    private function phone(string $phone): ?array
    {
        $digits = Str::of($phone)->replaceMatches('/\D/', '')->replaceMatches('/^229/', '')->toString();

        return strlen($digits) >= 8 ? ['number' => $digits, 'country' => $this->config['country'] ?? 'bj'] : null;
    }
}
