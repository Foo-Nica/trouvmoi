<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmation de paiement envoyée au client, avec le lien privé de ses photos. */
class OrderPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->order->event;

        return (new MailMessage)
            ->subject("Vos photos · {$event->title}")
            ->greeting("Bonjour {$this->order->customer_name},")
            ->line("Merci ! Votre paiement de {$this->order->formattedAmount()} (commande {$this->order->reference}) est confirmé.")
            ->line('Vos photos en version originale sont prêtes à être téléchargées.')
            ->action('Télécharger mes photos', $this->order->privateUrl())
            ->line('Gardez ce lien : il vous permet de retélécharger vos photos.');
    }
}
