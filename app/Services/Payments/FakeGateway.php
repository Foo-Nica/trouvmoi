<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Paiement simulé pour la démonstration en local : aucun argent n'est débité.
 * La page de simulation n'existe que lorsque ce moyen de paiement est actif.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(Order $order): RedirectResponse|View
    {
        return redirect()->route('order.simulate', $order->access_token);
    }

    public function verify(Order $order, Request $request): PaymentResult
    {
        if (! app(PaymentManager::class)->simulationAllowed()) {
            return PaymentResult::pending();
        }

        return match ($request->query('simulation')) {
            'paid' => PaymentResult::paid('SIM-'.$order->reference),
            'failed' => PaymentResult::failed(),
            default => PaymentResult::pending(),
        };
    }

    public function orderFromWebhook(Request $request): ?Order
    {
        return null;
    }
}
