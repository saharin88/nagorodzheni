<?php

namespace App\Filament\Admin\Resources\Awardees\Tables;

use App\Filament\Admin\Resources\Decrees\DecreeResource;
use App\Models\Awardee;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AwardeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(components: [
                TextColumn::make('rank')
                    ->label(__('Rank'))
                    ->alignCenter()
                    ->limit(length: 30)
                    ->tooltip(fn (?string $state): ?string => mb_strlen($state ?? '') > 30 ? $state : null)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label(__('Awardee full name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('award.name')
                    ->label(__('Award'))
                    ->suffix(fn (
                        ?string $state,
                        Awardee $record
                    ): string => $record->is_posthumous ? ' '.__('(posthumous)') : '')
                    ->toggleable(),
                TextColumn::make('decree.number')
                    ->label(__('Decree number'))
                    ->alignCenter()
                    ->url(fn (?string $state, Awardee $record): string => DecreeResource::getFilteredIndexUrl([
                        'search' => $state,
                    ]))
                    ->color('primary')
                    ->toggleable(),
                TextColumn::make('decree.date')
                    ->label(__('Decree date'))
                    ->alignCenter()
                    ->sortable()
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rank')
                    ->label(__('Rank'))
                    ->multiple()
                    ->options(fn (): array => Awardee::query()
                        ->selectRaw('rank, COUNT(*) as rank_count')
                        ->groupBy('rank')
                        ->orderByDesc('rank_count')
                        ->pluck('rank', 'rank')
                        ->toArray()
                    )
                    ->optionsLimit(limit: 10)
                    ->searchable(),
                SelectFilter::make('award')
                    ->label(__('Award'))
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->relationship('award', 'name'),
                SelectFilter::make('decree')
                    ->label(__('Decree'))
                    ->relationship(
                        'decree',
                        'number',
                        fn (Builder $query): Builder => $query->orderByDesc('date'),
                    )
                    ->searchable()
                    ->multiple()
                    ->optionsLimit(limit: 10)
                    ->preload(),
                SelectFilter::make('is_posthumous')
                    ->label(__('Posthumous'))
                    ->options([
                        '1' => __('Yes'),
                        '0' => __('No'),
                    ]),
            ])
            ->filtersFormColumns(2)
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
