<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Photo;
use App\Notifications\OrderPaid;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Création des commandes, confirmation des paiements et droits de téléchargement. */
class OrderPayments
{
    public function __construct(private readonly PaymentManager $payments) {}

    /**
     * @param  Collection<int, Photo>  $photos
     * @param  array{customer_name: string, customer_phone: string, customer_email?: string|null}  $customer
     */
    public function create(Event $event, Collection $photos, array $customer, ?Guest $guest, bool $pack = false): Order
    {
        return DB::transaction(function () use ($event, $photos, $customer, $guest, $pack) {
            $amount = $pack ? (int) $event->packOffer() : $photos->sum(fn (Photo $photo) => $event->priceFor($photo));

            $order = Order::create([
                'event_id' => $event->id,
                'guest_id' => $guest?->id,
                'customer_name' => $customer['customer_name'],
                'customer_phone' => $customer['customer_phone'],
                'customer_email' => $customer['customer_email'] ?? null,
                'is_pack' => $pack,
                'amount' => $amount,
                'currency' => config('trouvmoi.payments.currency'),
                'gateway' => $this->payments->default()->name(),
            ]);

            $order->items()->createMany($photos->map(fn (Photo $photo) => [
                'photo_id' => $photo->id,
                'price' => $pack ? 0 : $event->priceFor($photo),
            ])->all());

            return $order;
        });
    }

    /** Vérifie le paiement auprès du prestataire et met la commande à jour. */
    public function confirm(Order $order, Request $request): PaymentResult
    {
        if ($order->isPaid()) {
            return PaymentResult::paid((string) $order->gateway_reference);
        }

        if ($order->gateway === 'manual') {
            return PaymentResult::pending();
        }

        $result = $this->payments->driver($order->gateway)->verify($order, $request);

        if ($result->isPaid()) {
            $this->markPaid($order, $result->transactionId);
        } elseif ($result->isFailed()) {
            $order->markFailed();
        } elseif ($result->transactionId && ! $order->gateway_reference) {
            $order->forceFill(['gateway_reference' => $result->transactionId])->save();
        }

        return $result;
    }

    public function markPaid(Order $order, ?string $transactionId): void
    {
        $wasPaid = $order->isPaid();
        $order->markPaid($transactionId);

        if (! $wasPaid && $order->customer_email) {
            Notification::route('mail', $order->customer_email)->notify(new OrderPaid($order));
        }
    }

    /** Photos téléchargeables d'une commande payée (forfait : toutes les photos visibles, même ajoutées après). */
    public function downloadablePhotos(Order $order): Collection
    {
        $query = $order->is_pack
            ? Photo::where('event_id', $order->event_id)->where('is_visible', true)
            : $order->photos();

        return $query->whereNotNull('thumb_path')->orderByRaw('COALESCE(photos.taken_at, photos.created_at)')->get();
    }

    /** Photos déjà achetées par cet invité pour l'événement (ou toutes s'il a pris le forfait). */
    public function purchasedPhotoIds(Event $event, ?Guest $guest): PurchasedPhotos
    {
        if (! $guest) {
            return new PurchasedPhotos([], null);
        }

        $orders = Order::where('event_id', $event->id)->where('guest_id', $guest->id)->where('status', OrderStatus::Paid)->latest('paid_at')->get();
        $pack = $orders->firstWhere('is_pack', true);
        $byPhoto = [];
        foreach ($orders->where('is_pack', false) as $order) {
            foreach ($order->items()->pluck('photo_id') as $photoId) {
                $byPhoto[$photoId] ??= $order;
            }
        }

        return new PurchasedPhotos($byPhoto, $pack);
    }
}
