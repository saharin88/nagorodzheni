<?php

namespace App\Filament\Admin\Resources\Decrees\Tables;

use App\Models\Decree;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DecreesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('date')
                    ->alignCenter()
                    ->date()
                    ->sortable(),
                TextColumn::make('url')
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
                        ->selectRaw('YEAR(date) as year')
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
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
