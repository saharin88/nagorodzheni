<?php

namespace App\Filament\User\Resources\Awards;

use App\Filament\Traits\HasFilterableUrls;
use App\Filament\Traits\IsReadOnlyResource;
use App\Filament\User\Resources\Awards\Pages\ListAwards;
use App\Filament\User\Resources\Awards\Tables\AwardsTable;
use App\Models\Award;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AwardResource extends Resource
{
    use HasFilterableUrls;
    use IsReadOnlyResource;

    protected static ?string $model = Award::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    public static function getNavigationLabel(): string
    {
        return __('Awards');
    }

    public static function getModelLabel(): string
    {
        return __('Award');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Awards');
    }

    public static function table(Table $table): Table
    {
        return AwardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAwards::route('/'),
        ];
    }
}
