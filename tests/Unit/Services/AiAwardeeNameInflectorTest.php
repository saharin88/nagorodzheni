<?php

use App\Ai\Agents\UkrainianNameInflector;
use App\Contracts\Contracts\AwardeeNameInflector;
use App\Exceptions\AwardeeNameInflectionException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\DeepSeekProvider;
use Tests\TestCase;

uses(TestCase::class);

it('converts a full name to the genitive case', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => 'Іваненка Івана Івановича']]],
    ]);

    $inflector = app(AwardeeNameInflector::class);

    expect($inflector->toGenitive('Іваненко Іван Іванович'))
        ->toBe('Іваненка Івана Івановича');

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Постав кожне ПІБ у родовий відмінок')
            && $prompt->contains('1. Іваненко Іван Іванович');
    });
});

it('restores the nominative case from a full name in the genitive case', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => 'Іваненко Іван Іванович']]],
    ]);

    $inflector = app(AwardeeNameInflector::class);

    expect($inflector->fromGenitive('Іваненка Івана Івановича'))
        ->toBe('Іваненко Іван Іванович');

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Постав кожне ПІБ у називний відмінок')
            && $prompt->contains('1. Іваненка Івана Івановича');
    });
});

it('inflects many names with a single request', function () {
    UkrainianNameInflector::fake([
        ['names' => [
            ['index' => 1, 'full_name' => 'Шевченко Тарас Григорович'],
            ['index' => 2, 'full_name' => 'Ковальчук Марія Степанівна'],
            ['index' => 3, 'full_name' => 'Іваненко Іван Іванович'],
        ]],
    ]);

    $inflector = app(AwardeeNameInflector::class);

    expect($inflector->fromGenitiveMany([
        'Шевченка Тараса Григоровича',
        'Ковальчук Марії Степанівни',
        'Іваненка Івана Івановича',
    ]))->toBe([
        'Шевченко Тарас Григорович',
        'Ковальчук Марія Степанівна',
        'Іваненко Іван Іванович',
    ]);

    UkrainianNameInflector::assertPromptedTimes(1);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('3. Іваненка Івана Івановича');
    });
});

it('maps the inflected names back by their index', function () {
    UkrainianNameInflector::fake([
        ['names' => [
            ['index' => 2, 'full_name' => 'Шевченка Тараса Григоровича'],
            ['index' => 1, 'full_name' => 'Іваненка Івана Івановича'],
        ]],
    ]);

    expect(app(AwardeeNameInflector::class)->toGenitiveMany([
        'Іваненко Іван Іванович',
        'Шевченко Тарас Григорович',
    ]))->toBe([
        'Іваненка Івана Івановича',
        'Шевченка Тараса Григоровича',
    ]);
});

it('sends a duplicated name to the agent only once', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => 'Іваненка Івана Івановича']]],
    ]);

    expect(app(AwardeeNameInflector::class)->toGenitiveMany([
        'Іваненко Іван Іванович',
        'Іваненко Іван Іванович',
    ]))->toBe([
        'Іваненка Івана Івановича',
        'Іваненка Івана Івановича',
    ]);

    UkrainianNameInflector::assertPromptedTimes(1);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('1. Іваненко Іван Іванович')
            && ! $prompt->contains('2. Іваненко Іван Іванович');
    });
});

it('normalizes whitespace in the names before prompting the agent', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => 'Іваненка Івана Івановича']]],
    ]);

    expect(app(AwardeeNameInflector::class)->toGenitiveMany(['  Іваненко   Іван  Іванович  ']))
        ->toBe(['Іваненка Івана Івановича']);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('1. Іваненко Іван Іванович')
            && ! $prompt->contains('  ');
    });
});

it('normalizes whitespace in the names returned by the agent', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => '  Іваненка   Івана  Івановича  ']]],
    ]);

    expect(app(AwardeeNameInflector::class)->toGenitive('Іваненко Іван Іванович'))
        ->toBe('Іваненка Івана Івановича');
});

it('skips blank names without prompting the agent for them', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => 'Іваненка Івана Івановича']]],
    ]);

    expect(app(AwardeeNameInflector::class)->toGenitiveMany(['   ', 'Іваненко Іван Іванович']))
        ->toBe(['', 'Іваненка Івана Івановича']);
});

it('does not prompt the agent when every name is blank', function () {
    UkrainianNameInflector::fake();

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany(['', '   ']))
        ->toBe(['', '']);

    UkrainianNameInflector::assertNeverPrompted();
});

it('throws an exception when the agent returns no names', function () {
    UkrainianNameInflector::fake([[]]);

    expect(fn () => app(AwardeeNameInflector::class)->toGenitive('Іваненко Іван Іванович'))
        ->toThrow(AwardeeNameInflectionException::class, 'the agent returned no names');
});

it('throws an exception when the agent returns an unexpected entry', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 5, 'full_name' => 'Іваненка Івана Івановича']]],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->toGenitive('Іваненко Іван Іванович'))
        ->toThrow(AwardeeNameInflectionException::class, 'unknown entry [5]');
});

it('throws an exception when the agent returns an incomplete list', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => 'Іваненка Івана Івановича']]],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->toGenitiveMany([
        'Іваненко Іван Іванович',
        'Шевченко Тарас Григорович',
    ]))->toThrow(AwardeeNameInflectionException::class, '1 of 2 names were returned');
});

it('throws an exception when the agent returns a blank name', function () {
    UkrainianNameInflector::fake([
        ['names' => [['index' => 1, 'full_name' => '   ']]],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->toGenitive('Іваненко Іван Іванович'))
        ->toThrow(AwardeeNameInflectionException::class, 'Unable to inflect the awardee name [Іваненко Іван Іванович].');
});

it('inflects names with the deepseek provider', function () {
    $resolvedProvider = null;

    UkrainianNameInflector::fake(function ($prompt, $attachments, $provider, $model) use (&$resolvedProvider) {
        $resolvedProvider = $provider;

        return ['names' => [['index' => 1, 'full_name' => 'Іваненка Івана Івановича']]];
    });

    app(AwardeeNameInflector::class)->toGenitive('Іваненко Іван Іванович');

    expect($resolvedProvider)->toBeInstanceOf(DeepSeekProvider::class)
        ->and($resolvedProvider->driver())->toBe('deepseek');
});
