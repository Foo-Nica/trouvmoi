<?php

namespace App\Http\Controllers;

use App\Services\Orders\OrderPayments;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Throwable;

/**
 * Notifications serveur à serveur de KKiaPay et FedaPay. Le contenu reçu n'est jamais cru tel quel :
 * la commande est retrouvée puis son paiement revérifié auprès de l'API du prestataire.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentManager $payments, OrderPayments $orders): Response
    {
        try {
            $order = $payments->driver($gateway)->orderFromWebhook($request);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if (! $order) {
            return response('ignored', 202);
        }

        try {
            $orders->confirm($order, $request);
        } catch (Throwable $e) {
            report($e);

            return response('retry', 503);
        }

        return response('ok');
    }
}
