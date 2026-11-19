<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Moyen de paiement : KKiaPay, FedaPay ou simulation locale. */
interface PaymentGateway
{
    public function name(): string;

    /** Envoie le client vers la page de paiement (redirection ou widget). */
    public function start(Order $order): RedirectResponse|View;

    /**
     * Vérifie le paiement auprès du prestataire (jamais sur la seule foi du navigateur)
     * et renvoie la référence de transaction si la commande est bien payée.
     */
    public function verify(Order $order, Request $request): PaymentResult;

    /** Commande concernée par une notification serveur (webhook) authentique, sinon null. */
    public function orderFromWebhook(Request $request): ?Order;
}
