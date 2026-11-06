<?php

namespace App\Filament\Studio\Resources\Events\Pages;

use App\Enums\AccessMode;
use App\Filament\Studio\Resources\Events\EventResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function beforeCreate(): void
    {
        $user = auth()->user();
        $limit = $user->planLimits()['events_per_month'];

        if ($limit !== null && $user->events()->where('created_at', '>=', now()->startOfMonth())->count() >= $limit) {
            Notification::make()
                ->title('Limite de votre forfait atteinte')
                ->body("Votre forfait permet {$limit} événement(s) par mois. Passez à un forfait supérieur pour en créer davantage.")
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $pin = $this->data['pin'] ?? null;

        if ($this->record->access_mode === AccessMode::Pin && filled($pin)) {
            $this->record->setPin($pin);
            $this->record->save();
        }
    }

    protected function getRedirectUrl(): string
    {
        return EventResource::getUrl('photos', ['record' => $this->record]);
    }
}
