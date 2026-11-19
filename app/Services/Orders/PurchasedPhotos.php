<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Photo;

/** Ce qu'un invité a déjà payé pour un événement. */
final readonly class PurchasedPhotos
{
    /** @param  array<string, Order>  $orders  commande par identifiant de photo */
    public function __construct(private array $orders, public ?Order $pack) {}

    public function orderFor(Photo $photo): ?Order
    {
        return $this->pack ?? ($this->orders[$photo->id] ?? null);
    }

    public function owns(Photo $photo): bool
    {
        return $this->orderFor($photo) !== null;
    }
}
