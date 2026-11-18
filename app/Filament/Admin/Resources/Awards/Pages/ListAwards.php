<?php

namespace App\Filament\Admin\Resources\Awards\Pages;

use App\Filament\Admin\Resources\Awards\AwardResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAwards extends ListRecords
{
    protected static string $resource = AwardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Add award'))
                ->modalHeading(__('Add award'))
                ->modalSubmitActionLabel(__('Add'))
                ->modalWidth(Width::Medium)
                ->createAnother(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Award added'))
                ),
        ];
    }
}
