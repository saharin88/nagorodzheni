<?php

use App\Mcp\Servers\AwardeeServer;
use App\Mcp\Tools\ListAwardsTool;
use App\Mcp\Tools\ListDecreeAwardeesTool;
use App\Mcp\Tools\SearchAwardeesTool;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('registers both awardee tools', function () {
    AwardeeServer::tools()->assertRegistered([
        SearchAwardeesTool::class,
        ListDecreeAwardeesTool::class,
        ListAwardsTool::class,
    ]);
});

it('finds an awardee by any part of the name in any grammatical case', function (string $query) {
    $award = Award::factory()->create(['name' => 'орденом «За мужність» ІІІ ступеня']);
    $decree = Decree::factory()->create([
        'number' => '1131/2026',
        'date' => '2026-11-23',
        'url' => decreeUrl('1131-2026'),
    ]);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $award->getKey(),
        'full_name' => 'Антонюка Івана Олександровича',
        'full_name_nominative' => 'Антонюк Іван Олександрович',
        'rank' => 'молодшого лейтенанта',
    ]);

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => $query])
        ->assertOk()
        ->assertSee(['| Звання | ПІБ нагородженого | Нагорода | Номер указу | Дата указу |'])
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('returned', 1)
        ->assertStructuredContentPath('awardees.0.full_name', 'Антонюка Івана Олександровича')
        ->assertStructuredContentPath('awardees.0.full_name_nominative', 'Антонюк Іван Олександрович')
        ->assertStructuredContentPath('awardees.0.award', 'орденом «За мужність» ІІІ ступеня')
        ->assertStructuredContentPath('awardees.0.decree_number', '1131/2026')
        ->assertStructuredContentPath('awardees.0.decree_date', '2026-11-23');
})->with([
    'surname in the nominative case' => 'Антонюк',
    'surname in the genitive case' => 'Антонюка',
    'first name' => 'Іван',
    'patronymic' => 'Олександрович',
    'name in uppercase' => 'АНТОНЮК ІВАН',
    'reversed parts of the name' => 'Іван Антонюк',
    'full name in the genitive case' => 'Антонюка Івана Олександровича',
]);

it('matches a name with any apostrophe style', function (string $query) {
    Awardee::factory()->create([
        'full_name' => 'Корінного В’ячеслава Віталійовича',
        'full_name_nominative' => 'Корінний В’ячеслав Віталійович',
    ]);

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => $query])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('awardees.0.full_name_nominative', 'Корінний В’ячеслав Віталійович');
})->with([
    'straight apostrophe' => "Корінний В'ячеслав",
    'typographic apostrophe' => 'Корінний В’ячеслав',
    'no apostrophe at all' => 'Корінний Вячеслав',
]);

it('reports that nobody was found', function () {
    Awardee::factory()->create(['full_name' => 'Антонюка Івана Олександровича']);

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => 'Петренко'])
        ->assertOk()
        ->assertSee('нагороджених не знайдено')
        ->assertStructuredContentPath('total', 0)
        ->assertStructuredContentPath('returned', 0)
        ->assertStructuredContentCount(0, 'awardees');
});

it('returns the posthumous flag of an award', function () {
    Awardee::factory()->create([
        'full_name' => 'Безноска Костянтина Степановича',
        'full_name_nominative' => 'Безносок Костянтин Степанович',
        'is_posthumous' => true,
    ]);

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => 'Безносок'])
        ->assertOk()
        ->assertStructuredContentPath('awardees.0.is_posthumous', true);
});

it('limits the results and reports the total number of matches', function () {
    foreach (['Тараса Григоровича', 'Олену Іванівну', 'Петра Миколайовича'] as $name) {
        Awardee::factory()->create([
            'full_name' => "Шевченка {$name}",
            'full_name_nominative' => "Шевченко {$name}",
        ]);
    }

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => 'Шевченко', 'limit' => 2])
        ->assertOk()
        ->assertSee('знайдено нагороджень: 3')
        ->assertStructuredContentPath('total', 3)
        ->assertStructuredContentPath('returned', 2)
        ->assertStructuredContentCount(2, 'awardees');
});

it('lists every awardee of the decree in the order of the decree', function () {
    $decree = Decree::factory()->create([
        'number' => '601/2025',
        'date' => '2025-08-24',
        'url' => decreeUrl('601-2025'),
    ]);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'full_name' => 'Антонюка Івана Олександровича',
        'full_name_nominative' => 'Антонюк Іван Олександрович',
        'rank' => 'молодшого лейтенанта',
    ]);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'full_name' => 'Безноска Костянтина Степановича',
        'full_name_nominative' => 'Безносок Костянтин Степанович',
        'rank' => 'майора',
        'is_posthumous' => true,
    ]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '601/2025'])
        ->assertOk()
        ->assertSee([
            'Указ № 601/2025 від 24.08.2025',
            '| молодшого лейтенанта | Антонюка Івана Олександровича |',
            '| майора | Безноска Костянтина Степановича |',
            '(посмертно) | 601/2025 | 24.08.2025 |',
        ])
        ->assertStructuredContentPath('decree.number', '601/2025')
        ->assertStructuredContentPath('decree.date', '2025-08-24')
        ->assertStructuredContentPath('total', 2)
        ->assertStructuredContentPath('last_page', 1)
        ->assertStructuredContentPath('awardees.0.full_name', 'Антонюка Івана Олександровича')
        ->assertStructuredContentPath('awardees.1.full_name', 'Безноска Костянтина Степановича')
        ->assertStructuredContentPath('awardees.1.is_posthumous', true);
});

it('paginates the awardees of a decree', function () {
    $decree = Decree::factory()->create(['number' => '601/2025', 'date' => '2025-08-24']);

    foreach (['Антонюка Івана Олександровича', 'Безноска Костянтина Степановича', 'Бровчука Петра Адамовича'] as $name) {
        Awardee::factory()->create([
            'decree_id' => $decree->getKey(),
            'full_name' => $name,
        ]);
    }

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '601', 'page' => 2, 'per_page' => 2])
        ->assertOk()
        ->assertSee('Показано записи 3–3 (сторінка 2 із 2).')
        ->assertStructuredContentPath('page', 2)
        ->assertStructuredContentPath('per_page', 2)
        ->assertStructuredContentPath('last_page', 2)
        ->assertStructuredContentPath('total', 3)
        ->assertStructuredContentPath('returned', 1)
        ->assertStructuredContentPath('awardees.0.full_name', 'Бровчука Петра Адамовича');
});

it('reports an unknown decree number', function () {
    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '1131/2026'])
        ->assertHasErrors()
        ->assertSee('не знайдено');
});

it('refuses to guess a decree number that exists in several years', function () {
    Decree::factory()->create(['number' => '1131/2025', 'date' => '2025-12-01']);
    Decree::factory()->create(['number' => '1131/2026', 'date' => '2026-11-23']);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '1131'])
        ->assertHasErrors()
        ->assertSee(['1131/2025', '1131/2026']);
});

it('rejects a page beyond the last page of a decree', function () {
    $decree = Decree::factory()->create(['number' => '601/2025', 'date' => '2025-08-24']);

    Awardee::factory()->create(['decree_id' => $decree->getKey()]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '601/2025', 'page' => 2])
        ->assertHasErrors()
        ->assertSee('лише 1 записів');
});

it('rejects too large page numbers for decree listing', function () {
    Decree::factory()->create(['number' => '601/2025', 'date' => '2025-08-24']);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '601/2025', 'page' => 201])
        ->assertHasErrors()
        ->assertSee('At most 200 pages can be requested in one call.');
});

it('rejects too large page numbers for search', function () {
    Awardee::factory()->create(['full_name' => 'Шевченка Тараса Григоровича']);

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => 'Шевченко', 'page' => 201])
        ->assertHasErrors()
        ->assertSee('At most 200 pages can be requested in one call.');
});

it('rejects overly broad name filters in search', function () {
    AwardeeServer::tool(SearchAwardeesTool::class, [
        'query' => 'один два три чотири пʼять шість сім',
    ])
        ->assertHasErrors()
        ->assertSee('Передайте не більше 6 слів у query.');
});

it('rejects overly broad award filters in search', function () {
    AwardeeServer::tool(SearchAwardeesTool::class, [
        'award' => 'орден за військову службу україні третього ступеня особливої відваги та честі держави України',
    ])
        ->assertHasErrors()
        ->assertSee('Передайте не більше 12 слів у award.');
});

it('rejects overly broad award filters in decree listing', function () {
    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => 'latest',
        'award' => 'орден за військову службу україні третього ступеня особливої відваги та честі держави України',
    ])
        ->assertHasErrors()
        ->assertSee('Передайте не більше 12 слів у award.');
});

it('merges the posthumous marker into the award column', function () {
    $award = Award::factory()->create(['name' => 'орденом «За мужність» ІІІ ступеня']);

    Awardee::factory()->create([
        'award_id' => $award->getKey(),
        'full_name' => 'Безноска Костянтина Степановича',
        'full_name_nominative' => 'Безносок Костянтин Степанович',
        'rank' => 'майора',
        'is_posthumous' => true,
    ]);

    AwardeeServer::tool(SearchAwardeesTool::class, ['query' => 'Безносок'])
        ->assertOk()
        ->assertSee('| майора | Безноска Костянтина Степановича | орденом «За мужність» ІІІ ступеня (посмертно) |')
        ->assertStructuredContentPath('awardees.0.is_posthumous', true)
        ->assertStructuredContentPath('awardees.0.award', 'орденом «За мужність» ІІІ ступеня');
});
