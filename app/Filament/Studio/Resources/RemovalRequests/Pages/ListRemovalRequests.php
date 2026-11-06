<?php

namespace App\Filament\Studio\Resources\RemovalRequests\Pages;

use App\Filament\Studio\Resources\RemovalRequests\RemovalRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRemovalRequests extends ListRecords
{
    protected static string $resource = RemovalRequestResource::class;
}
