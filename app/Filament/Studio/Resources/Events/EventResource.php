<?php

namespace App\Filament\Studio\Resources\Events;

use App\Filament\Studio\Resources\Events\Pages\CreateEvent;
use App\Filament\Studio\Resources\Events\Pages\EditEvent;
use App\Filament\Studio\Resources\Events\Pages\ListEvents;
use App\Filament\Studio\Resources\Events\Pages\ManageEventPhotos;
use App\Filament\Studio\Resources\Events\Schemas\EventForm;
use App\Filament\Studio\Resources\Events\Tables\EventsTable;
use App\Filament\Studio\Resources\Events\Widgets\EventStatsOverview;
use App\Models\Event;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'événements';

    protected static ?string $navigationLabel = 'Mes événements';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /** Un photographe ne voit que ses événements et ceux où il est membre. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->accessibleBy(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditEvent::class,
            ManageEventPhotos::class,
        ]);
    }

    public static function getWidgets(): array
    {
        return [EventStatsOverview::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
            'photos' => ManageEventPhotos::route('/{record}/photos'),
        ];
    }
}
