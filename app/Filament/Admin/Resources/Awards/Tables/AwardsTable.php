<?php

namespace App\Filament\Admin\Resources\Awards\Tables;

use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Models\Award;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
