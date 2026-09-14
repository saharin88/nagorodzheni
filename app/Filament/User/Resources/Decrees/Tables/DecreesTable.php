<?php

namespace App\Filament\User\Resources\Decrees\Tables;

use App\Filament\User\Resources\Awardees\AwardeeResource;
use App\Models\Decree;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DecreesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('Decree number'))
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('Decree date'))
                    ->alignCenter()
                    ->date()
                    ->sortable(),
                TextColumn::make('awardees_count')
                    ->label(__('Awardees'))
                    ->counts([
                        'awardees',
                        'awardees as posthumous_awardees_count' => fn (Builder $query): Builder => $query->where('is_posthumous', true),
                    ])
                    ->url(fn ($state, Decree $record): ?string => $state > 0 ? AwardeeResource::getFilteredIndexUrl([
                        'decree' => [$record->getKey()],
                    ]) : null)
                    ->suffix(fn (Decree $record): string => $record->posthumous_awardees_count > 0
                        ? ' '.__('(:count posthumous)', ['count' => $record->posthumous_awardees_count])
                        : '')
                    ->alignCenter()
                    ->color('primary')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('url')
                    ->label(__('Decree URL'))
                    ->url(fn (string $state): string => $state, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->searchable()
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
            ->filters([
                SelectFilter::make('decree_year')
                    ->label(__('Year'))
                    ->options(fn (): array => Decree::query()
                        ->whereNotNull('date')
                        ->selectRaw(match (DB::connection()->getDriverName()) {
                            'sqlite' => "strftime('%Y', date) as year",
                            'pgsql' => "to_char(date, 'YYYY') as year",
                            default => 'YEAR(date) as year',
                        })
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->toArray()
                    )
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, $year): Builder => $query->whereBetween('date', [
                            "{$year}-01-01 00:00:00",
                            "{$year}-12-31 23:59:59",
                        ])
                    )),
            ]);
    }
}
