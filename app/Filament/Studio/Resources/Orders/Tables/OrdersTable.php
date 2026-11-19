<?php

namespace App\Filament\Studio\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderPayments;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Liste des ventes, commune à /studio et /admin. */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucune vente pour le moment')
            ->emptyStateDescription('Les achats des invités et des personnalités apparaîtront ici.')
            ->columns([
                TextColumn::make('reference')->label('Référence')->searchable()->fontFamily('mono')->copyable(),
                TextColumn::make('event.title')->label('Événement')->limit(30)->searchable()
                    ->description(fn (Order $record): ?string => $record->event?->client_name),
                TextColumn::make('customer_name')->label('Client')->searchable()
                    ->description(fn (Order $record): string => $record->customer_phone),
                TextColumn::make('items_count')->label('Photos')->counts('items')->alignEnd()
                    ->formatStateUsing(fn (Order $record, $state): string => $record->is_pack ? 'Forfait' : (string) $state),
                TextColumn::make('amount')->label('Montant')->alignEnd()->weight('semibold')
                    ->formatStateUsing(fn (int $state): string => Order::formatPrice($state)),
                TextColumn::make('status')->label('Statut')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
                TextColumn::make('gateway')->label('Paiement')->toggleable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'kkiapay' => 'KKiaPay',
                        'fedapay' => 'FedaPay',
                        'manual' => 'Manuel',
                        default => 'Démonstration',
                    }),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(OrderStatus::options()),
                SelectFilter::make('event')->label('Événement')->relationship('event', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Page client')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Order $record): string => $record->privateUrl(), shouldOpenInNewTab: true),
                Action::make('markPaid')
                    ->label('Marquer payée')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Order $record): bool => ! $record->isPaid())
                    ->requiresConfirmation()
                    ->modalDescription('À utiliser pour un paiement reçu en main propre (espèces, virement). Le client pourra télécharger ses photos depuis son lien.')
                    ->action(function (Order $record): void {
                        $record->forceFill(['gateway' => 'manual'])->save();
                        app(OrderPayments::class)->markPaid($record, 'MANUEL');
                        Notification::make()->title("Commande {$record->reference} payée")->success()->send();
                    }),
            ]);
    }
}
