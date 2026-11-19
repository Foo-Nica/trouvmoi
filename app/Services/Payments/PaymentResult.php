<?php

namespace App\Services\Payments;

/** Résultat d'une vérification de paiement auprès du prestataire. */
final readonly class PaymentResult
{
    private function __construct(
        public string $state,
        public ?string $transactionId = null,
    ) {}

    public static function paid(string $transactionId): self
    {
        return new self('paid', $transactionId);
    }

    public static function failed(?string $transactionId = null): self
    {
        return new self('failed', $transactionId);
    }

    public static function pending(?string $transactionId = null): self
    {
        return new self('pending', $transactionId);
    }

    public function isPaid(): bool
    {
        return $this->state === 'paid';
    }

    public function isFailed(): bool
    {
        return $this->state === 'failed';
    }
}
