<?php

namespace App\Filament\Studio\Widgets;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tableau de bord de l'équipe : événements, photos et ventes. */
class StudioOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $events = Event::accessibleBy(auth()->user());
        $eventIds = (clone $events)->select('id');
        $orders = Order::whereIn('event_id', $eventIds);
        $paidThisMonth = (clone $orders)->where('status', OrderStatus::Paid)->where('paid_at', '>=', now()->startOfMonth());

        return [
            Stat::make('Événements en ligne', (clone $events)->where('status', EventStatus::Published)->count())
                ->description((clone $events)->count().' au total')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Photos publiées', number_format(Photo::whereIn('event_id', $eventIds)->whereNotNull('thumb_path')->count(), 0, ',', ' '))
                ->icon('heroicon-o-photo'),
            Stat::make('Ventes du mois', Order::formatPrice((int) (clone $paidThisMonth)->sum('amount')))
                ->description((clone $paidThisMonth)->count().' commande(s) payée(s)')
                ->color('success')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Paiements en attente', (clone $orders)->where('status', OrderStatus::Pending)->count())
                ->icon('heroicon-o-clock'),
        ];
    }
}
