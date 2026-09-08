<?php

namespace App\Filament\Admin\Resources\Decrees;

use App\Filament\Admin\Resources\Decrees\Pages\CreateDecree;
use App\Filament\Admin\Resources\Decrees\Pages\EditDecree;
use App\Filament\Admin\Resources\Decrees\Pages\ListDecrees;
use App\Filament\Admin\Resources\Decrees\Schemas\DecreeForm;
use App\Filament\Admin\Resources\Decrees\Tables\DecreesTable;
use App\Models\Decree;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DecreeResource extends Resource
{
    protected static ?string $model = Decree::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DecreeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DecreesTable::configure($table);
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
            'index' => ListDecrees::route('/'),
            'create' => CreateDecree::route('/create'),
            'edit' => EditDecree::route('/{record}/edit'),
        ];
    }
}
