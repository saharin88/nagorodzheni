<?php

namespace App\Filament\Admin\Resources\Awards\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AwardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('Award name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                FileUpload::make('image_path')
                    ->label(__('Award image'))
                    ->disk('public')
                    ->directory('award-images')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/svg+xml',
                    ])
                    ->maxSize(1024)
                    ->imagePreviewHeight('160')
                    ->imageEditor()
                    ->imageEditorAspectRatioOptions(['1:1'])
                    ->automaticallyResizeImagesMode('cover')
                    ->automaticallyResizeImagesToWidth('512')
                    ->automaticallyResizeImagesToHeight('512')
                    ->automaticallyUpscaleImagesWhenResizing(false)
                    ->preventFilePathTampering()
                    ->openable(),
            ]);
    }
}
