<?php

namespace App\Filament\Studio\Resources\Events\Pages;

use App\Filament\Studio\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvel événement'),
        ];
    }
}
