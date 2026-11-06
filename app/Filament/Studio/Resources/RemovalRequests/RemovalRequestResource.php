<?php

namespace App\Filament\Studio\Resources\RemovalRequests;

use App\Filament\Studio\Resources\RemovalRequests\Pages\ListRemovalRequests;
use App\Models\Event;
use App\Models\RemovalRequest;
use App\Services\Photos\PhotoRemover;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Demandes de retrait envoyées par les invités, à traiter sous 72 h. */
class RemovalRequestResource extends Resource
{
    protected static ?string $model = RemovalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $modelLabel = 'demande de retrait';

    protected static ?string $pluralModelLabel = 'demandes de retrait';

    protected static ?string $navigationLabel = 'Demandes de retrait';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('event_id', Event::accessibleBy(auth()->user())->select('id'))
            ->with(['event', 'photo']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucune demande de retrait')
            ->columns([
                ImageColumn::make('thumb')->label('')
                    ->getStateUsing(fn (RemovalRequest $r) => $r->photo?->temporaryUrl('thumb'))
                    ->imageHeight(56),
                TextColumn::make('event.title')->label('Événement')->limit(30),
                TextColumn::make('reason')->label('Motif')->placeholder('—')->wrap()->limit(80),
                TextColumn::make('contact')->label('Contact')->placeholder('—'),
                TextColumn::make('status')->label('Statut')->badge()
                    ->formatStateUsing(fn (string $state) => ['pending' => 'À traiter', 'done' => 'Photo retirée', 'rejected' => 'Refusée'][$state] ?? $state)
                    ->color(fn (string $state) => ['pending' => 'danger', 'done' => 'success', 'rejected' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Reçue')->since(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')
                    ->options(['pending' => 'À traiter', 'done' => 'Photo retirée', 'rejected' => 'Refusée'])
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Retirer la photo')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('La photo et ses données faciales seront supprimées définitivement.')
                    ->visible(fn (RemovalRequest $r) => $r->status === 'pending' && $r->photo)
                    ->action(function (RemovalRequest $r) {
                        app(PhotoRemover::class)->remove($r->photo);
                        $r->forceFill(['status' => 'done', 'handled_at' => now()])->save();
                        Notification::make()->title('Photo retirée')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Refuser')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (RemovalRequest $r) => $r->status === 'pending')
                    ->action(fn (RemovalRequest $r) => $r->forceFill(['status' => 'rejected', 'handled_at' => now()])->save()),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListRemovalRequests::route('/')];
    }
}
