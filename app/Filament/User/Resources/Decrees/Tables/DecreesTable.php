<?php

namespace App\Filament\User\Resources\Decrees\Tables;

use App\Filament\User\Resources\Awardees\AwardeeResource;
use App\Models\Decree;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DecreesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(function (Builder $query): Builder {
                /** @var Builder<Decree> $query */
                $query->orderBy('date', 'desc')->orderByNumber('desc');

                return $query;
            })
            ->columns([
                TextColumn::make('number')
                    ->label(__('Decree number'))
                    ->alignCenter()
                    ->searchable()
                    ->icon(fn (Decree $record): ?Heroicon => $record->is_hero ? Heroicon::Star : null)
                    ->iconColor('warning')
                    ->iconPosition(IconPosition::After)
                    ->tooltip(fn (Decree $record): array|string|null => $record->is_hero
                        ? __('Decree about conferring the Hero of Ukraine title')
                        : null),
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
                        ? ' '.($record->posthumous_awardees_count < $record->awardees_count
                            ? __('(:count posthumous)', ['count' => $record->posthumous_awardees_count])
                            : __('(posthumous)'))
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
                TernaryFilter::make('is_hero')
                    ->label(__('Hero of Ukraine title'))
                    ->placeholder(__('All decrees')),
            ]);
    }
}
