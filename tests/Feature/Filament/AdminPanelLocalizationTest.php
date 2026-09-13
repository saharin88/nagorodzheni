<?php

use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Filament\Admin\Resources\Awards\AwardResource;
use App\Filament\Admin\Resources\Decrees\DecreeResource;

it('translates the admin panel resource labels', function () {
    expect(AwardResource::getNavigationLabel())->toBe('Нагороди')
        ->and(AwardResource::getModelLabel())->toBe('Нагорода')
        ->and(AwardResource::getPluralModelLabel())->toBe('Нагороди')
        ->and(AwardeeResource::getNavigationLabel())->toBe('Нагороджені')
        ->and(AwardeeResource::getModelLabel())->toBe('Нагороджений')
        ->and(AwardeeResource::getPluralModelLabel())->toBe('Нагороджені')
        ->and(DecreeResource::getNavigationLabel())->toBe('Укази')
        ->and(DecreeResource::getModelLabel())->toBe('Указ')
        ->and(DecreeResource::getPluralModelLabel())->toBe('Укази');
});

it('translates the framework validation messages', function () {
    expect(__('validation.required', ['attribute' => 'звання']))
        ->toBe('Поле звання є обов\'язковим.')
        ->and(__('auth.failed'))
        ->toBe('Ці дані не збігаються з нашими записами.');
});
