<?php

namespace App\Filament\Studio\Resources\Events\Tables;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use App\Filament\Studio\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Event $record) => EventResource::getUrl('photos', ['record' => $record]))
            ->emptyStateHeading('Aucun événement pour le moment')
            ->emptyStateDescription('Créez votre premier événement pour obtenir son code QR et envoyer vos photos.')
            ->columns([
                TextColumn::make('title')
                    ->label('Événement')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (Event $record) => collect([$record->event_date?->format('d/m/Y'), $record->location])->filter()->join(' · ')),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (EventStatus $state) => $state->label())
                    ->color(fn (EventStatus $state) => $state->color()),
                TextColumn::make('access_mode')
                    ->label('Accès')
                    ->formatStateUsing(fn (AccessMode $state) => $state->label())
                    ->toggleable(),
                TextColumn::make('photos_count')->label('Photos')->numeric()->alignEnd(),
                TextColumn::make('faces_count')->label('Visages')->numeric()->alignEnd()->toggleable(),
                TextColumn::make('scans_count')->label('Scans')->numeric()->alignEnd(),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->date('d/m/Y')
                    ->color(fn (Event $record) => $record->isExpired() ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(EventStatus::options()),
            ])
            ->recordActions([
                Action::make('photos')
                    ->label('Photos')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->url(fn (Event $record) => EventResource::getUrl('photos', ['record' => $record])),
                ActionGroup::make([
                    Action::make('qr')
                        ->label('Télécharger le QR (PNG)')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->url(fn (Event $record) => route('studio.events.qr', [$record, 'png'])),
                    Action::make('public')
                        ->label('Ouvrir la page invités')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Event $record) => $record->publicUrl(), shouldOpenInNewTab: true),
                    EditAction::make(),
                ]),
            ]);
    }
}
