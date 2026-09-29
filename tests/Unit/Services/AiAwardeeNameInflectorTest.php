<?php

use App\Ai\Agents\UkrainianNameInflector;
use App\Contracts\AwardeeNameInflector;
use App\Exceptions\AwardeeNameInflectionException;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Get the lab that the group's agent declares through its #[Provider] attribute,
 * so the tests keep following the configured provider instead of hard-coding it.
 */
function inflectorDeclaredLab(): Lab
{
    $attributes = (new ReflectionClass(UkrainianNameInflector::class))->getAttributes(Provider::class);

    expect($attributes)->toHaveCount(1);

    $lab = $attributes[0]->newInstance()->value;

    expect($lab)->toBeInstanceOf(Lab::class);

    return $lab;
}

it('restores the nominative case from a full name in the genitive case', function () {
    UkrainianNameInflector::fake([
        ['names' => ['Іваненко Іван Іванович']],
    ]);

    $inflector = app(AwardeeNameInflector::class);

    expect($inflector->fromGenitive('Іваненка Івана Івановича'))
        ->toBe('Іваненко Іван Іванович');

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Постав кожне ПІБ у називний відмінок')
            && $prompt->contains('1. Іваненка Івана Івановича');
    });
});

it('restores many names with a single request', function () {
    UkrainianNameInflector::fake([
        ['names' => [
            'Шевченко Тарас Григорович',
            'Ковальчук Марія Степанівна',
            'Іваненко Іван Іванович',
        ]],
    ]);

    $inflector = app(AwardeeNameInflector::class);

    expect($inflector->fromGenitiveMany([
        'Шевченка Тараса Григоровича',
        'Ковальчук Марії Степанівни',
        'Іваненка Івана Івановича',
    ]))->toBe([
        'Шевченка Тараса Григоровича' => 'Шевченко Тарас Григорович',
        'Ковальчук Марії Степанівни' => 'Ковальчук Марія Степанівна',
        'Іваненка Івана Івановича' => 'Іваненко Іван Іванович',
    ]);

    UkrainianNameInflector::assertPromptedTimes(1);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('3. Іваненка Івана Івановича');
    });
});

it('maps every restored name to the source name it was asked about', function () {
    UkrainianNameInflector::fake([
        ['names' => [
            'Іваненко Іван Іванович',
            'Шевченко Тарас Григорович',
        ]],
    ]);

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany([
        'Іваненка Івана Івановича',
        'Шевченка Тараса Григоровича',
    ]))->toBe([
        'Іваненка Івана Івановича' => 'Іваненко Іван Іванович',
        'Шевченка Тараса Григоровича' => 'Шевченко Тарас Григорович',
    ]);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains("1. Іваненка Івана Івановича\n2. Шевченка Тараса Григоровича");
    });
});

it('sends a duplicated name to the agent only once', function () {
    UkrainianNameInflector::fake([
        ['names' => ['Іваненко Іван Іванович']],
    ]);

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany([
        'Іваненка Івана Івановича',
        'Іваненка Івана Івановича',
    ]))->toBe([
        'Іваненка Івана Івановича' => 'Іваненко Іван Іванович',
    ]);

    UkrainianNameInflector::assertPromptedTimes(1);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('1. Іваненка Івана Івановича')
            && ! $prompt->contains('2. Іваненка Івана Івановича');
    });
});

it('normalizes whitespace in the names before prompting the agent', function () {
    UkrainianNameInflector::fake([
        ['names' => ['Іваненко Іван Іванович']],
    ]);

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany(['  Іваненка   Івана  Івановича  ']))
        ->toBe(['Іваненка Івана Івановича' => 'Іваненко Іван Іванович']);

    UkrainianNameInflector::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('1. Іваненка Івана Івановича')
            && ! $prompt->contains('  ');
    });
});

it('normalizes whitespace in the names returned by the agent', function () {
    UkrainianNameInflector::fake([
        ['names' => ['  Іваненко   Іван  Іванович  ']],
    ]);

    expect(app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toBe('Іваненко Іван Іванович');
});

it('skips blank names without prompting the agent for them', function () {
    UkrainianNameInflector::fake([
        ['names' => ['Іваненко Іван Іванович']],
    ]);

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany(['   ', 'Іваненка Івана Івановича']))
        ->toBe(['Іваненка Івана Івановича' => 'Іваненко Іван Іванович']);
});

it('does not prompt the agent when every name is blank', function () {
    UkrainianNameInflector::fake();

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany(['', '   ']))
        ->toBe([]);

    UkrainianNameInflector::assertNeverPrompted();
});

it('does not prompt the agent when there are no names at all', function () {
    UkrainianNameInflector::fake();

    expect(app(AwardeeNameInflector::class)->fromGenitiveMany([]))->toBe([]);

    UkrainianNameInflector::assertNeverPrompted();
});

it('restores the names with the provider declared by the agent', function () {
    $resolvedProvider = null;

    UkrainianNameInflector::fake(function ($prompt, $attachments, $provider, $model) use (&$resolvedProvider) {
        $resolvedProvider = $provider;

        return ['names' => ['Іваненко Іван Іванович']];
    });

    expect(app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toBe('Іваненко Іван Іванович');

    expect($resolvedProvider)->not->toBeNull()
        ->and($resolvedProvider->driver())->toBe(inflectorDeclaredLab()->value);
});

it('disables the reasoning that DeepSeek would bill as output tokens', function () {
    $agent = new UkrainianNameInflector;

    expect($agent)->toBeInstanceOf(HasProviderOptions::class)
        ->and($agent->providerOptions(Lab::DeepSeek))->toBe([
            'thinking' => ['type' => 'disabled'],
            'max_tokens' => 131072,
        ]);
});

it('throws an exception when the agent returns no names', function () {
    UkrainianNameInflector::fake([[]]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toThrow(AwardeeNameInflectionException::class, 'агент не повернув жодного ПІБ');
});

it('throws an exception when the agent returns names that are not a list', function () {
    UkrainianNameInflector::fake([
        ['names' => 'Іваненко Іван Іванович'],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toThrow(AwardeeNameInflectionException::class, 'агент не повернув жодного ПІБ');
});

it('throws an exception when the agent returns an unexpected entry', function () {
    UkrainianNameInflector::fake([
        ['names' => ['перший' => 'Іваненко Іван Іванович']],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toThrow(AwardeeNameInflectionException::class, 'агент повернув неочікуваний запис');
});

it('throws an exception when the agent returns more names than requested', function () {
    UkrainianNameInflector::fake([
        ['names' => ['Іваненко Іван Іванович', 'Шевченко Тарас Григорович']],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toThrow(AwardeeNameInflectionException::class, 'повернуто 2 із 1');
});

it('throws an exception when the agent returns a non-string name', function () {
    UkrainianNameInflector::fake([
        ['names' => [null]],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toThrow(AwardeeNameInflectionException::class, 'агент повернув неочікуваний запис');
});

it('throws an exception when the agent returns an incomplete list', function () {
    UkrainianNameInflector::fake([
        ['names' => ['Іваненко Іван Іванович']],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitiveMany([
        'Іваненка Івана Івановича',
        'Шевченка Тараса Григоровича',
    ]))->toThrow(AwardeeNameInflectionException::class, 'повернуто 1 із 2');
});

it('throws an exception when the agent returns a blank name', function () {
    UkrainianNameInflector::fake([
        ['names' => ['   ']],
    ]);

    expect(fn () => app(AwardeeNameInflector::class)->fromGenitive('Іваненка Івана Івановича'))
        ->toThrow(AwardeeNameInflectionException::class, 'Не вдалося відмінити ПІБ [Іваненка Івана Івановича].');
});
