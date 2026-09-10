<?php

namespace App\Filament\Admin\Resources\Decrees\Pages;

use App\Filament\Admin\Resources\Decrees\DecreeResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDecrees extends ListRecords
{
    protected static string $resource = DecreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Add decree'))
                ->modalHeading('')
                ->modalSubmitAction(false)
                ->createAnother(false)
                ->modalCancelAction(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Decree added'))
                ),
        ];
    }
}
