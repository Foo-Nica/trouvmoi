<?php

namespace App\Filament\Studio\Resources\Events\Schemas;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use App\Models\Photo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informations')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label("Nom de l'événement")
                            ->placeholder('Mariage de Awa & Koffi')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        DatePicker::make('event_date')
                            ->label('Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('location')
                            ->label('Lieu')
                            ->placeholder('Cotonou, Bénin')
                            ->maxLength(150),
                        Textarea::make('description')
                            ->label('Message aux invités')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        FileUpload::make('cover_path')
                            ->label('Photo de couverture')
                            ->image()
                            ->disk(fn () => Photo::disk())
                            ->directory('covers')
                            ->visibility('private')
                            ->maxSize(10 * 1024)
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),

                Section::make('Accès des invités')
                    ->columnSpanFull()
                    ->schema([
                        Radio::make('access_mode')
                            ->label('Comment les invités retrouvent leurs photos')
                            ->options(AccessMode::options())
                            ->descriptions(collect(AccessMode::cases())->mapWithKeys(fn (AccessMode $m) => [$m->value => $m->description()])->all())
                            ->default(AccessMode::Gallery->value)
                            ->live()
                            ->required(),
                        TextInput::make('pin')
                            ->label('Code PIN')
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Laisser vide pour garder le code actuel.'
                                : 'À communiquer aux invités avec le QR code.')
                            ->numeric()
                            ->minLength(4)
                            ->maxLength(8)
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->required(fn (Get $get, string $operation) => $get('access_mode') === AccessMode::Pin->value && $operation === 'create')
                            ->visible(fn (Get $get) => $get('access_mode') === AccessMode::Pin->value),
                        Toggle::make('has_minors')
                            ->label('Événement avec des mineurs')
                            ->helperText('Désactive la reconnaissance faciale : les invités parcourent seulement la galerie.'),
                    ]),

                Section::make('Photos et publication')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Toggle::make('watermark_enabled')
                            ->label('Filigrane sur les photos')
                            ->helperText('Votre nom sur les aperçus et les téléchargements.')
                            ->default(true),
                        Select::make('retention_days')
                            ->label('Conservation après l\'événement')
                            ->options([30 => '30 jours', 60 => '60 jours', 90 => '90 jours', 180 => '6 mois'])
                            ->default(config('trouvmoi.default_retention_days'))
                            ->selectablePlaceholder(false)
                            ->helperText('Ensuite, les données faciales sont supprimées.'),
                        Select::make('status')
                            ->label('Statut')
                            ->options(EventStatus::options())
                            ->default(EventStatus::Draft->value)
                            ->selectablePlaceholder(false)
                            ->helperText('« En ligne » : la page est accessible par le QR code.'),
                    ]),
            ]);
    }
}
