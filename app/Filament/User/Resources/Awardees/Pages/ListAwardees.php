<?php

namespace App\Filament\User\Resources\Awardees\Pages;

use App\Filament\User\Resources\Awardees\AwardeeResource;
use Filament\Resources\Pages\ListRecords;

class ListAwardees extends ListRecords
{
    protected static string $resource = AwardeeResource::class;
}
