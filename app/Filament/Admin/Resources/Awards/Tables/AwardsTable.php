<?php

namespace App\Filament\Admin\Resources\Awards\Tables;

use App\Contracts\Contracts\AwardMerger;
use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Models\Award;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AwardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Award name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('awardees_count')
                    ->label(__('Awardees'))
                    ->counts('awardees')
                    ->url(fn ($state, Award $record): ?string => $state > 0 ? AwardeeResource::getFilteredIndexUrl([
                        'award' => [$record->getKey()],
                    ]) : null)
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mergeAwards')
                        ->label(__('Merge awards'))
                        ->icon(Heroicon::OutlinedArrowsPointingIn)
                        ->requiresConfirmation()
                        ->modalHeading(__('Merge awards'))
                        ->modalDescription(__('The awardees of the selected awards are linked to the award with the most awardees. The other selected awards are deleted.'))
                        ->modalSubmitActionLabel(__('Merge'))
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, AwardMerger $awardMerger): void {
                            try {
                                $primaryAward = $awardMerger->merge($records);
                            } catch (InvalidArgumentException $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Unable to merge awards'))
                                    ->body($exception->getMessage())
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title(__('Awards merged'))
                                ->body(__('The awardees are linked to ":award" now. Deleted awards: :count', [
                                    'award' => $primaryAward->name,
                                    'count' => $records->count() - 1,
                                ]))
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
