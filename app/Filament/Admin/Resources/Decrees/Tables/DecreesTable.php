<?php

namespace App\Filament\Admin\Resources\Decrees\Tables;

use App\Exceptions\AwardeeNameInflectionException;
use App\Exceptions\DecreeParseException;
use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Models\Decree;
use App\Services\DecreeAwardeeImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

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
                    ->url(fn (string $state): string => $state, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('awardees_count')
                    ->label(__('Awardees'))
                    ->counts('awardees')
                    ->url(fn ($state, Decree $record): ?string => $state > 0 ? AwardeeResource::getFilteredIndexUrl([
                        'decree' => [$record->getKey()],
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
            ])
            ->recordActions([
                Action::make('importAwardees')
                    ->label(__('Import awardees and awards'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->requiresConfirmation()
                    ->modalHeading(__('Import awardees and awards'))
                    ->modalDescription(__('Import the awardees of this decree together with their awards?'))
                    ->modalSubmitActionLabel(__('Import'))
                    ->action(function (Decree $record, DecreeAwardeeImporter $decreeAwardeeImporter): void {
                        try {
                            $importedAwardeesCount = $decreeAwardeeImporter->import($record);
                        } catch (InvalidArgumentException|RequestException|DecreeParseException|AwardeeNameInflectionException $exception) {
                            Log::error('Failed to import decree awardees', [
                                'decree' => $record->getKey(),
                                'exception' => $exception,
                            ]);

                            Notification::make()
                                ->danger()
                                ->title(__('Unable to import awardees and awards'))
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('Awardees and awards imported'))
                            ->body(__('Imported awardees: :count', ['count' => $importedAwardeesCount]))
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
