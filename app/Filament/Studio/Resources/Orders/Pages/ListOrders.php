<?php

namespace App\Filament\Studio\Resources\Orders\Pages;

use App\Filament\Studio\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
