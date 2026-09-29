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

it('translates the nominative names interface of the awardees table', function () {
    expect(__('Awardee full name (nominative case)'))->toBe('ПІБ нагородженого (називний відмінок)')
        ->and(__('Missing nominative name'))->toBe('Відсутній ПІБ у називному відмінку')
        ->and(__('Full name equals nominative'))->toBe('ПІБ збігається з називним відмінком')
        ->and(__('Fill nominative names'))->toBe('Заповнити ПІБ у називному відмінку')
        ->and(__('Run nominative names inflection for selected awardees where this field is empty?'))
        ->toBe('Запустити відмінювання у називний відмінок для вибраних нагороджених, де це поле порожнє?')
        ->and(__('Nominative names are being generated in the background.'))
        ->toBe('ПІБ у називному відмінку буде згенеровано у фоновому режимі.')
        ->and(__('The decree list came back empty [:url].', ['url' => 'https://example.com']))
        ->toBe('Список указів виявився порожнім [https://example.com].')
        ->and(__('Unable to parse decree info from URL: :url', ['url' => 'https://example.com']))
        ->toBe('Не вдалося розібрати дані указу з URL: https://example.com');
});

it('translates the framework validation messages', function () {
    expect(__('validation.required', ['attribute' => 'звання']))
        ->toBe('Поле звання є обов\'язковим.')
        ->and(__('auth.failed'))
        ->toBe('Ці дані не збігаються з нашими записами.');
});
