<?php

namespace App\Filament\Admin\Resources\Decrees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DecreeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('url')
                            ->url()
                            ->required(),
                        TextInput::make('number')
                            ->required(),
                        DatePicker::make('date')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->format('Y-m-d')
                            ->required(),
                    ])
                    ->inlineLabel(),
            ]);
    }
}
