<?php

namespace App\Filament\Admin\Resources\Awardees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AwardeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label(__('Awardee full name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('rank')
                    ->label(__('Rank'))
                    ->required()
                    ->maxLength(255),
                Select::make('decree_id')
                    ->label(__('Decree'))
                    ->relationship('decree', 'number')
                    ->searchable()
                    ->preload()
                    ->disabled(fn (string $context) => $context === 'edit')
                    ->required(),
                Select::make('award_id')
                    ->label(__('Award'))
                    ->relationship('award', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->columns(1);
    }
}
