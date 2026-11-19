<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Achat de photos en version originale par un invité ou une personnalité.
 * Le lien privé (access_token) donne accès aux téléchargements une fois la commande payée.
 */
#[Fillable([
    'event_id', 'guest_id', 'customer_name', 'customer_phone', 'customer_email', 'is_pack',
    'amount', 'currency', 'status', 'gateway', 'gateway_reference', 'paid_at',
])]
#[Hidden(['access_token'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => 'pending',
        'currency' => 'XOF',
        'is_pack' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'is_pack' => 'boolean',
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->reference ??= static::uniqueReference();
            $order->access_token ??= Str::random(48);
        });
    }

    /** Référence lisible communiquée au client et au moyen de paiement, ex. TRE-7K3QX9. */
    public static function uniqueReference(): string
    {
        do {
            $reference = 'TRE-'.strtoupper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'order_items')->withPivot('price')->withTimestamps();
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function markPaid(?string $gatewayReference = null): void
    {
        if ($this->isPaid()) {
            return;
        }

        $this->forceFill([
            'status' => OrderStatus::Paid,
            'gateway_reference' => $gatewayReference ?? $this->gateway_reference,
            'paid_at' => now(),
        ])->save();
    }

    public function markFailed(): void
    {
        if (! $this->isPaid()) {
            $this->forceFill(['status' => OrderStatus::Failed])->save();
        }
    }

    public function privateUrl(): string
    {
        return route('order.show', $this->access_token);
    }

    public function formattedAmount(): string
    {
        return static::formatPrice($this->amount);
    }

    public static function formatPrice(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }
}
