<?php

namespace App\Filament\User\Resources\Awards\Tables;

use App\Filament\User\Resources\Awardees\AwardeeResource;
use App\Models\Award;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AwardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(50)
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Award name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('awardees_count')
                    ->label(__('Awardees'))
                    ->counts([
                        'awardees',
                        'awardees as posthumous_awardees_count' => fn (Builder $query): Builder => $query->where('is_posthumous', true),
                    ])
                    ->url(fn ($state, Award $record): ?string => $state > 0 ? AwardeeResource::getFilteredIndexUrl([
                        'award' => [$record->getKey()],
                    ]) : null)
                    ->suffix(fn (Award $record): string => $record->posthumous_awardees_count > 0
                        ? ' '.__('(:count posthumous)', ['count' => $record->posthumous_awardees_count])
                        : '')
                    ->color('primary')
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
            ]);
    }
}
