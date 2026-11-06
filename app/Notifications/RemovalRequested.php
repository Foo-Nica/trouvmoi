<?php

namespace App\Notifications;

use App\Filament\Studio\Resources\RemovalRequests\RemovalRequestResource;
use App\Models\RemovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Prévient le photographe qu'un invité demande le retrait d'une photo (à traiter sous 72 h). */
class RemovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RemovalRequest $removalRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->removalRequest->event;

        return (new MailMessage)
            ->subject("Demande de retrait d'une photo · {$event->title}")
            ->greeting('Bonjour,')
            ->line("Un invité de l'événement « {$event->title} » demande le retrait d'une photo.")
            ->when($this->removalRequest->reason, fn (MailMessage $mail) => $mail->line('Motif : '.$this->removalRequest->reason))
            ->line('Merci de traiter cette demande sous 72 heures.')
            ->action('Voir la demande', RemovalRequestResource::getUrl('index', panel: 'studio'));
    }
}
