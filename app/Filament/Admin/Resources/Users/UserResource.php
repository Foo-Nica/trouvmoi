<?php

namespace App\Filament\Admin\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'photographe';

    protected static ?string $pluralModelLabel = 'photographes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $plans = collect(config('trouvmoi.plans'))->map(fn ($p) => $p['label'])->all();

        return $schema->components([
            Section::make('Compte')->columns(2)->schema([
                TextInput::make('name')->label('Nom')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('business_name')->label('Nom commercial'),
                TextInput::make('phone')->label('Téléphone')->tel(),
                Select::make('role')->label('Rôle')
                    ->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()]))
                    ->required(),
                Toggle::make('is_active')->label('Compte actif')->inline(false),
            ]),
            Section::make('Abonnement mensuel')->columns(2)->schema([
                Select::make('plan')->label('Forfait')->options($plans)->required(),
                DateTimePicker::make('plan_ends_at')->label('Fin de la période payée')->native(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->description(fn (User $u) => $u->business_name),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('role')->label('Rôle')->badge()->formatStateUsing(fn (UserRole $state) => $state->label()),
                TextColumn::make('plan')->label('Forfait')->badge()
                    ->formatStateUsing(fn (string $state) => config("trouvmoi.plans.{$state}.label", $state)),
                TextColumn::make('plan_ends_at')->label('Payé jusqu\'au')->date('d/m/Y')
                    ->color(fn (User $u) => $u->plan_ends_at?->isPast() ? 'danger' : null),
                TextColumn::make('events_count')->label('Événements')->counts('events')->alignEnd(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
                TextColumn::make('created_at')->label('Inscrit le')->date('d/m/Y')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('plan')->label('Forfait')
                    ->options(collect(config('trouvmoi.plans'))->map(fn ($p) => $p['label'])->all()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
