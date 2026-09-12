<?php

namespace App\Filament\Admin\Resources\Awardees\Pages;

use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAwardees extends ListRecords
{
    protected static string $resource = AwardeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Add awardee'))
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Awardee added'))
                ),
        ];
    }
}
