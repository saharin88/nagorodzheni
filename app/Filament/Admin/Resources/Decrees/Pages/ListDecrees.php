<?php

namespace App\Filament\Admin\Resources\Decrees\Pages;

use App\Contracts\AwardDecreeSynchronizer;
use App\Exceptions\AwardeeNameInflectionException;
use App\Exceptions\DecreeParseException;
use App\Filament\Admin\Resources\Decrees\DecreeResource;
use App\Models\Decree;
use App\Services\DecreeAwardeeImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ListDecrees extends ListRecords
{
    protected static string $resource = DecreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncDecrees')
                ->label(__('Sync decrees'))
                ->requiresConfirmation()
                ->modalHeading(__('Sync decrees and awardees'))
                ->modalDescription(__('Run synchronization of decrees and awardees now?'))
                ->modalSubmitActionLabel(__('Sync'))
                ->action(function (AwardDecreeSynchronizer $synchronizer): void {
                    try {
                        $result = $synchronizer->sync();
                    } catch (DecreeParseException $exception) {
                        Log::error('Failed to run manual decree sync', [
                            'exception' => $exception,
                        ]);

                        Notification::make()
                            ->danger()
                            ->title(__('Unable to synchronize decrees'))
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    $notification = Notification::make()
                        ->title(
                            $result['skipped'] > 0
                                ? __('Synchronization completed with errors')
                                : __('Synchronization completed')
                        )
                        ->body(__('New decrees: :added, Imported awardees: :awardees, Skipped decrees: :skipped', [
                            'added' => $result['added'],
                            'awardees' => $result['awardees'],
                            'skipped' => $result['skipped'],
                        ]));

                    if ($result['skipped'] > 0) {
                        $notification->warning()->send();

                        return;
                    }

                    $notification->success()->send();
                }),
            CreateAction::make()
                ->label(__('Add decree'))
                ->modalHeading('')
                ->modalSubmitAction(false)
                ->createAnother(false)
                ->modalCancelAction(false)
                ->after(function (
                    array $arguments,
                    Decree $record,
                    DecreeAwardeeImporter $decreeAwardeeImporter,
                    CreateAction $action,
                ): void {
                    if (! ($arguments['importAwardees'] ?? false)) {
                        return;
                    }

                    try {
                        $importedAwardeesCount = $decreeAwardeeImporter->import($record);

                        Notification::make()
                            ->success()
                            ->title(__('Awardees imported'))
                            ->body(__('Imported awardees: :count', ['count' => $importedAwardeesCount]))
                            ->send();
                    } catch (InvalidArgumentException|RequestException|DecreeParseException|AwardeeNameInflectionException $exception) {
                        Log::error('Failed to import decree awardees right after creation', [
                            'decree' => $record->getKey(),
                            'exception' => $exception,
                        ]);

                        Notification::make()
                            ->danger()
                            ->title(__('Unable to import awardees and awards'))
                            ->body($exception->getMessage())
                            ->send();

                        $action->halt(shouldRollBackDatabaseTransaction: true);
                    }
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Decree added'))
                ),
        ];
    }
}
