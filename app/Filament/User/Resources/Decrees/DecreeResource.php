<?php

namespace App\Filament\User\Resources\Decrees;

use App\Filament\Traits\HasFilterableUrls;
use App\Filament\Traits\IsReadOnlyResource;
use App\Filament\User\Resources\Decrees\Pages\ListDecrees;
use App\Filament\User\Resources\Decrees\Tables\DecreesTable;
use App\Models\Decree;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DecreeResource extends Resource
{
    use HasFilterableUrls;
    use IsReadOnlyResource;

    protected static ?string $model = Decree::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function getNavigationLabel(): string
    {
        return __('Decrees');
    }

    public static function getModelLabel(): string
    {
        return __('Decree');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Decrees');
    }

    public static function table(Table $table): Table
    {
        return DecreesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDecrees::route('/'),
        ];
    }
}
