<?php

namespace App\Filament\Admin\Resources\Awardees\Tables;

use App\Filament\Admin\Resources\Decrees\DecreeResource;
use App\Models\Award;
use App\Models\Awardee;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
                    ->url(fn (?string $state, Awardee $record): string => DecreeResource::getFilteredIndexUrl(
                        search: $state
                    ))
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
                    BulkAction::make('changeAward')
                        ->label(__('Change award'))
                        ->icon(Heroicon::OutlinedTrophy)
                        ->modalHeading(__('Change award'))
                        ->modalDescription(__('The selected awardees are linked to the award you choose.'))
                        ->modalSubmitActionLabel(__('Change'))
                        ->modalWidth(Width::Small)
                        ->deselectRecordsAfterCompletion()
                        ->schema([
                            Select::make('award_id')
                                ->label(__('Award'))
                                ->options(fn (): array => Award::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                                )
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $award = Award::query()->whereKey($data['award_id'])->firstOrFail();

                            Awardee::query()
                                ->whereKey($records->pluck('id')->all())
                                ->update(['award_id' => $award->getKey()]);

                            Notification::make()
                                ->success()
                                ->title(__('Award changed'))
                                ->body(__('The awardees are linked to ":award" now. Updated awardees: :count', [
                                    'award' => $award->name,
                                    'count' => $records->count(),
                                ]))
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
