<?php

namespace App\Filament\Admin\Resources\Decrees;

use App\Filament\Admin\Resources\Decrees\Pages\ListDecrees;
use App\Filament\Admin\Resources\Decrees\Schemas\DecreeForm;
use App\Filament\Admin\Resources\Decrees\Tables\DecreesTable;
use App\Filament\Traits\HasFilterableUrls;
use App\Models\Decree;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DecreeResource extends Resource
{
    use HasFilterableUrls;

    protected static ?string $model = Decree::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return DecreeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DecreesTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
        ];
    }
}
