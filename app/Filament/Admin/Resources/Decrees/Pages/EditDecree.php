<?php

namespace App\Filament\Admin\Resources\Decrees\Pages;

use App\Filament\Admin\Resources\Decrees\DecreeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDecree extends EditRecord
{
    protected static string $resource = DecreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
