<?php

namespace App\Filament\Admin\Resources\Events;

use App\Enums\EventStatus;
use App\Filament\Admin\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'événements';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Événement')->searchable()->description(fn (Event $e) => $e->slug),
                TextColumn::make('owner.name')->label('Photographe')->searchable(),
                TextColumn::make('status')->label('Statut')->badge()
                    ->formatStateUsing(fn (EventStatus $state) => $state->label())
                    ->color(fn (EventStatus $state) => $state->color()),
                TextColumn::make('photos_count')->label('Photos')->numeric()->alignEnd(),
                TextColumn::make('faces_count')->label('Visages')->numeric()->alignEnd(),
                TextColumn::make('scans_count')->label('Scans')->numeric()->alignEnd(),
                IconColumn::make('has_minors')->label('Mineurs')->boolean(),
                TextColumn::make('expires_at')->label('Expire le')->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(EventStatus::options()),
            ])
            ->recordActions([
                Action::make('public')->label('Page invités')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Event $e) => $e->publicUrl(), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEvents::route('/')];
    }
}
