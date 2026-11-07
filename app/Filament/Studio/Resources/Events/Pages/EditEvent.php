<?php

namespace App\Filament\Studio\Resources\Events\Pages;

use App\Enums\AccessMode;
use App\Filament\Studio\Resources\Events\EventResource;
use App\Jobs\ProcessPhoto;
use App\Jobs\PurgeEventFaces;
use App\Models\Photo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Réglages';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('qr_png')->label('QR code (PNG)')->url(fn () => route('studio.events.qr', [$this->record, 'png'])),
                Action::make('qr_svg')->label('QR code (SVG, impression)')->url(fn () => route('studio.events.qr', [$this->record, 'svg'])),
            ])
                ->label('QR code')
                ->icon(Heroicon::OutlinedQrCode)
                ->button(),
            Action::make('public')
                ->label('Page invités')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => $this->record->publicUrl(), shouldOpenInNewTab: true),
            DeleteAction::make()
                ->visible(fn () => $this->record->user_id === auth()->id())
                ->modalDescription('Les photos, les dossiers des invités et les données faciales de cet événement seront supprimés définitivement.')
                ->before(fn () => PurgeEventFaces::dispatchSync($this->record)),
        ];
    }

    protected function afterSave(): void
    {
        // Filigrane activé ou retiré : les images déjà publiées sont refaites.
        if ($this->record->wasChanged('watermark_enabled')) {
            $this->record->photos()->whereNotNull('thumb_path')->each(
                fn (Photo $photo) => ProcessPhoto::dispatch($photo, indexFaces: false)
            );

            Notification::make()
                ->title($this->record->watermark_enabled ? 'Ajout du filigrane en cours' : 'Retrait du filigrane en cours')
                ->body('Les photos déjà publiées sont mises à jour en arrière-plan.')
                ->info()
                ->send();
        }

        $pin = $this->data['pin'] ?? null;

        if ($this->record->access_mode !== AccessMode::Pin) {
            $this->record->pin_hash = null;
        } elseif (filled($pin)) {
            $this->record->setPin($pin);
        }

        if ($this->record->isDirty('pin_hash')) {
            $this->record->save();
        }

        // Un événement passé « avec mineurs » perd immédiatement ses données faciales.
        if ($this->record->has_minors && $this->record->faces_count > 0) {
            PurgeEventFaces::dispatch($this->record);
        }
    }
}
