<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * KKiaPay : le widget officiel s'ouvre sur notre page, puis renvoie le client avec l'identifiant
 * de transaction. Le paiement est confirmé côté serveur avec les clés privée et secrète.
 */
class KkiapayGateway implements PaymentGateway
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'kkiapay';
    }

    public function start(Order $order): RedirectResponse|View
    {
        if (empty($this->config['public_key'])) {
            throw new RuntimeException('KKiaPay : KKIAPAY_PUBLIC_KEY manquante dans le fichier .env.');
        }

        return view('payments.kkiapay', [
            'order' => $order,
            'publicKey' => $this->config['public_key'],
            'sandbox' => (bool) $this->config['sandbox'],
            'widgetUrl' => $this->config['widget_url'],
            'returnUrl' => route('order.return', $order->access_token),
        ]);
    }

    public function verify(Order $order, Request $request): PaymentResult
    {
        $transactionId = (string) ($request->query('transaction_id') ?: $order->gateway_reference);
        if ($transactionId === '') {
            return PaymentResult::pending();
        }

        // Une transaction ne peut payer qu'une seule commande.
        $usedElsewhere = Order::where('gateway', $this->name())
            ->where('gateway_reference', $transactionId)
            ->whereKeyNot($order->getKey())
            ->exists();
        if ($usedElsewhere) {
            return PaymentResult::failed();
        }

        $base = $this->config['sandbox'] ? $this->config['sandbox_api_url'] : $this->config['api_url'];
        $response = Http::baseUrl($base)
            ->acceptJson()
            ->timeout(20)
            ->withHeaders([
                'X-API-KEY' => (string) $this->config['public_key'],
                'X-PRIVATE-KEY' => (string) $this->config['private_key'],
                'X-SECRET-KEY' => (string) $this->config['secret'],
            ])
            ->post('/api/v1/transactions/status', ['transactionId' => $transactionId]);

        if (! $response->successful()) {
            return PaymentResult::pending($transactionId);
        }

        $status = strtoupper((string) $response->json('status'));
        $state = $response->json('state');
        $belongsToOrder = ! is_string($state) || $state === '' || $state === $order->reference;

        if ($status === 'SUCCESS' && $belongsToOrder && (int) $response->json('amount') >= $order->amount) {
            return PaymentResult::paid($transactionId);
        }

        return in_array($status, ['FAILED', 'INSUFFICIENT_FUND', 'TRANSACTION_NOT_FOUND'], true)
            ? PaymentResult::failed($transactionId)
            : PaymentResult::pending($transactionId);
    }

    public function orderFromWebhook(Request $request): ?Order
    {
        $secret = $this->config['webhook_secret'] ?? null;
        if ($secret && ! hash_equals($secret, (string) $request->header('x-kkiapay-secret'))) {
            return null;
        }

        $reference = $request->input('stateData') ?? $request->input('state');
        $transactionId = $request->input('transactionId');
        if (! is_string($reference) || ! $transactionId) {
            return null;
        }

        $order = Order::where('gateway', $this->name())->where('reference', $reference)->first();
        $order?->forceFill(['gateway_reference' => $order->gateway_reference ?? (string) $transactionId])->save();

        return $order;
    }
}
