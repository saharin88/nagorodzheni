<?php

namespace App\Filament\User\Resources\Awards\Pages;

use App\Filament\User\Resources\Awards\AwardResource;
use Filament\Resources\Pages\ListRecords;

class ListAwards extends ListRecords
{
    protected static string $resource = AwardResource::class;
}
