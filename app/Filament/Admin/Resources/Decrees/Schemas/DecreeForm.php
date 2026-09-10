<?php

namespace App\Filament\Admin\Resources\Decrees\Schemas;

use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use App\Filament\Admin\Resources\Decrees\Pages\ListDecrees;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class DecreeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('Decree URL'))
                        ->description(__('Enter the URL of the decree page for automatic data population'))
                        ->schema([
                            TextInput::make('url')
                                ->url()
                                ->required()
                                ->extraFieldWrapperAttributes(['class' => 'hide-field-error'])
                                ->belowContent(function (ListDecrees $livewire) {
                                    $errors = $livewire->getErrorBag()->all();

                                    if (count($errors) === 0) {
                                        return null;
                                    }

                                    return Text::make(new HtmlString(implode('<br>', array_map('e', $errors))))
                                        ->color('danger');
                                }),
                            Hidden::make('number')
                                ->unique(table: 'decrees', column: 'number')
                                ->validationMessages([
                                    'unique' => __('This decree has already been added.'),
                                ])
                                ->requiredIfAccepted('url'),
                            Hidden::make('date')
                                ->requiredIfAccepted('url'),
                        ])
                        ->beforeValidation(function (Get $get, Set $set, DecreeMetaParser $decreeMetaParser): void {
                            $url = $get('url');
                            if (blank($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                                return;
                            }

                            try {
                                $set('number', $decreeMetaParser->getDecreeNumber($url));
                                $set('date', $decreeMetaParser->getDecreeDate($url)->toDateString());
                            } catch (InvalidArgumentException|RequestException|DecreeParseException $exception) {
                                Log::error('Failed to parse decree meta', [
                                    'url' => $url,
                                    'exception' => $exception,
                                ]);

                                Notification::make()
                                    ->danger()
                                    ->title(__('Unable to retrieve decree data'))
                                    ->body($exception->getMessage())
                                    ->send();

                                throw new Halt;
                            }
                        }),
                    Step::make(__('Confirmation'))
                        ->inlineLabel()
                        ->description(__('Verify the data before adding the decree'))
                        ->schema([
                            TextEntry::make('review_number')
                                ->label(__('Decree number'))
                                ->state(fn (Get $get): string => (string) ($get('number') ?? '')),
                            TextEntry::make('review_date')
                                ->label(__('Decree date'))
                                ->state(fn (Get $get): string => (string) ($get('date') ?? '')),
                            TextEntry::make('review_url')
                                ->label(__('Decree URL'))
                                ->state(fn (Get $get): string => (string) ($get('url') ?? '')),
                        ]),
                ])
                    ->submitAction(
                        Action::make('create')
                            ->label(__('Added'))
                            ->submit('create')
                    )
                    ->columnSpanFull(),
            ]);
    }
}
