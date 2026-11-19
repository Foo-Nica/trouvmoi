<?php

namespace App\Filament\Studio\Resources\Orders;

use App\Filament\Studio\Resources\Orders\Pages\ListOrders;
use App\Filament\Studio\Resources\Orders\Tables\OrdersTable;
use App\Models\Event;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Ventes des photos (versions originales et forfaits) des événements de l'utilisateur. */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'vente';

    protected static ?string $pluralModelLabel = 'ventes';

    protected static ?string $navigationLabel = 'Ventes';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('event_id', Event::accessibleBy(auth()->user())->select('id'))
            ->with('event');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
