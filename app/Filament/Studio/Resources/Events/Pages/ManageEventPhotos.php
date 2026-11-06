<?php

namespace App\Filament\Studio\Resources\Events\Pages;

use App\Enums\PhotoStatus;
use App\Filament\Studio\Resources\Events\EventResource;
use App\Filament\Studio\Resources\Events\Widgets\EventStatsOverview;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Services\Photos\PhotoRemover;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ManageEventPhotos extends ManageRelatedRecords
{
    protected static string $resource = EventResource::class;

    protected static string $relationship = 'photos';

    protected static ?string $navigationLabel = 'Photos';

    protected static ?string $title = 'Photos';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-photo';
    }

    protected function getHeaderWidgets(): array
    {
        return [EventStatsOverview::class];
    }

    public function getSubheading(): ?string
    {
        $event = $this->getRecord();

        return sprintf('%s · %d photos · %d visages indexés', $event->title, $event->photos_count, $event->faces_count);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.studio.photo-uploader')->viewData([
                'event' => $this->getRecord(),
                'uploadUrl' => route('studio.events.photos.store', $this->getRecord()),
                'maxMb' => config('trouvmoi.upload.max_mb'),
            ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->paginated([24, 48, 96])
            ->defaultPaginationPageOption(48)
            ->emptyStateHeading('Aucune photo')
            ->emptyStateDescription('Glissez vos photos traitées dans la zone ci-dessus.')
            ->columns([
                ImageColumn::make('thumb')
                    ->label('')
                    ->getStateUsing(fn (Photo $record) => $record->thumb_path ? $record->temporaryUrl('thumb') : null)
                    ->imageHeight(64),
                TextColumn::make('original_name')->label('Fichier')->searchable()->limit(30),
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (PhotoStatus $state) => $state->label())
                    ->color(fn (PhotoStatus $state) => $state->color())
                    ->tooltip(fn (Photo $record) => $record->error),
                TextColumn::make('faces_count')->label('Visages')->numeric()->alignEnd(),
                ToggleColumn::make('is_visible')->label('Visible'),
                TextColumn::make('created_at')->label('Envoyée')->since()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('État')->options(
                    collect(PhotoStatus::cases())->mapWithKeys(fn (PhotoStatus $s) => [$s->value => $s->label()])
                ),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Relancer')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Photo $record) => $record->status === PhotoStatus::Failed)
                    ->action(function (Photo $record) {
                        $record->update(['status' => PhotoStatus::Received, 'error' => null]);
                        ProcessPhoto::dispatch($record);
                    }),
                Action::make('delete')
                    ->label('Supprimer')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Photo $record) => app(PhotoRemover::class)->remove($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide')
                        ->label('Masquer')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => Photo::whereKey($records->modelKeys())->update(['is_visible' => false])),
                    BulkAction::make('delete')
                        ->label('Supprimer')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn (Photo $photo) => app(PhotoRemover::class)->remove($photo));
                            Notification::make()->title($records->count().' photo(s) supprimée(s)')->success()->send();
                        }),
                ]),
            ]);
    }
}
