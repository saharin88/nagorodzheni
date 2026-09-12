<?php

namespace App\Filament\Admin\Resources\Awards;

use App\Filament\Admin\Resources\Awards\Pages\ListAwards;
use App\Filament\Admin\Resources\Awards\Schemas\AwardForm;
use App\Filament\Admin\Resources\Awards\Tables\AwardsTable;
use App\Filament\Traits\HasFilterableUrls;
use App\Models\Award;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AwardResource extends Resource
{
    use HasFilterableUrls;

    protected static ?string $model = Award::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Awards';

    protected static ?string $modelLabel = 'Award';

    protected static ?string $pluralModelLabel = 'Awards';

    public static function form(Schema $schema): Schema
    {
        return AwardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AwardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAwards::route('/'),
        ];
    }
}
