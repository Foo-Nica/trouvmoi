<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/** Choisit le moyen de paiement selon PAYMENT_DRIVER (ou celui d'une commande existante). */
class PaymentManager
{
    public function default(): PaymentGateway
    {
        return $this->driver(config('trouvmoi.payments.driver'));
    }

    public function driver(string $name): PaymentGateway
    {
        $config = config('trouvmoi.payments');

        return match ($name) {
            'kkiapay' => new KkiapayGateway($config['kkiapay']),
            'fedapay' => new FedaPayGateway($config['fedapay']),
            'fake' => new FakeGateway,
            default => throw new InvalidArgumentException("Moyen de paiement inconnu : {$name}"),
        };
    }

    /** La simulation n'est jamais disponible en production. */
    public function simulationAllowed(): bool
    {
        return config('trouvmoi.payments.driver') === 'fake' && ! app()->isProduction();
    }
}
