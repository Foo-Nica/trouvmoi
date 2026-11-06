<?php

namespace App\Filament\Studio\Resources\Events\Widgets;

use App\Enums\PhotoStatus;
use App\Models\ConsentLog;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Statistiques d'un événement : diffusion du QR, recherches, invités, téléchargements. */
class EventStatsOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        /** @var Event $event */
        $event = $this->record;

        $indexed = $event->photos()->where('status', PhotoStatus::Indexed)->count();
        $failed = $event->photos()->where('status', PhotoStatus::Failed)->count();
        $guests = ConsentLog::where('event_id', $event->id)->distinct()->count('guest_id');
        $downloads = (int) $event->downloads()->sum('photos_count');

        return [
            Stat::make('Photos', number_format($event->photos_count, 0, ',', ' '))
                ->description($failed > 0 ? "{$failed} en erreur" : number_format($indexed, 0, ',', ' ').' analysées')
                ->color($failed > 0 ? 'danger' : 'primary'),
            Stat::make('Scans du QR', number_format($event->scans_count, 0, ',', ' ')),
            Stat::make('Invités', number_format($guests, 0, ',', ' '))
                ->description($event->searches()->count().' recherches'),
            Stat::make('Photos téléchargées', number_format($downloads, 0, ',', ' '))
                ->description($event->folders()->count().' dossiers créés'),
        ];
    }
}
