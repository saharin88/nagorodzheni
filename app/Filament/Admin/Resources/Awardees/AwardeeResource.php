<?php

namespace App\Filament\Admin\Resources\Awardees;

use App\Filament\Admin\Resources\Awardees\Pages\ListAwardees;
use App\Filament\Admin\Resources\Awardees\Schemas\AwardeeForm;
use App\Filament\Admin\Resources\Awardees\Tables\AwardeesTable;
use App\Filament\Traits\HasFilterableUrls;
use App\Models\Awardee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AwardeeResource extends Resource
{
    use HasFilterableUrls;

    protected static ?string $model = Awardee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationLabel(): string
    {
        return __('Awardees');
    }

    public static function getModelLabel(): string
    {
        return __('Awardee');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Awardees');
    }

    public static function form(Schema $schema): Schema
    {
        return AwardeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AwardeesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['award', 'decree']);
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
            'index' => ListAwardees::route('/'),
        ];
    }
}
