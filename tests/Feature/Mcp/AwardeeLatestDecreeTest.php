<?php

use App\Mcp\Servers\AwardeeServer;
use App\Mcp\Tools\ListDecreeAwardeesTool;
use App\Mcp\Tools\SearchAwardeesTool;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;

beforeEach(function () {
    $this->order = Award::factory()->create(['name' => "орденом \u{00AB}За мужність\u{00BB} ІІІ ступеня"]);
    $this->medal = Award::factory()->create(['name' => "медаллю \u{00AB}За військову службу Україні\u{00BB}"]);
});

it('resolves the latest decree from the words of the user', function (string $decreeNumber) {
    Decree::factory()->create(['number' => '900/2026', 'date' => '2026-09-01', 'url' => decreeUrl('900-2026')]);
    $latest = Decree::factory()->create(['number' => '1131/2026', 'date' => '2026-11-23', 'url' => decreeUrl('1131-2026')]);

    Awardee::factory()->create([
        'decree_id' => $latest->getKey(),
        'award_id' => $this->order->getKey(),
        'rank' => 'капітана',
    ]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => $decreeNumber])
        ->assertOk()
        ->assertSee(['Останній указ у реєстрі — № 1131/2026 від 23.11.2026', 'капітана'])
        ->assertStructuredContentPath('decree.number', '1131/2026')
        ->assertStructuredContentPath('decree.resolved_as_latest', true)
        ->assertStructuredContentPath('total', 1);
})->with([
    'english token' => 'latest',
    'ukrainian token' => 'останній',
    'uppercase token' => 'LATEST',
]);

it('treats a missing decree number as the latest decree', function () {
    $latest = Decree::factory()->create(['number' => '1131/2026', 'date' => '2026-11-23']);

    Awardee::factory()->create(['decree_id' => $latest->getKey()]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [])
        ->assertOk()
        ->assertStructuredContentPath('decree.number', '1131/2026')
        ->assertStructuredContentPath('decree.resolved_as_latest', true);
});

it('prefers the newest decree by date even when a later number belongs to an older decree', function () {
    Decree::factory()->create(['number' => '1200/2025', 'date' => '2025-12-30']);
    $newest = Decree::factory()->create(['number' => '10/2026', 'date' => '2026-01-05']);

    Awardee::factory()->create(['decree_id' => $newest->getKey()]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => 'latest'])
        ->assertOk()
        ->assertStructuredContentPath('decree.number', '10/2026');
});

it('uses the newest decree that contains the requested award', function () {
    $withMedal = Decree::factory()->create(['number' => '1117/2026', 'date' => '2026-11-16']);
    $withOrder = Decree::factory()->create(['number' => '1100/2026', 'date' => '2026-11-02']);

    Awardee::factory()->create([
        'decree_id' => $withMedal->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);

    Awardee::factory()->create([
        'decree_id' => $withOrder->getKey(),
        'award_id' => $this->order->getKey(),
    ]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => 'latest',
        'award' => 'орден за мужність',
    ])
        ->assertOk()
        ->assertSee('Це найновіший указ, що відповідає фільтру.')
        ->assertStructuredContentPath('decree.number', '1100/2026')
        ->assertStructuredContentPath('decree.resolved_as_latest', true)
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('awardees.0.award', "орденом \u{00AB}За мужність\u{00BB} ІІІ ступеня");
});

it('reports that no decree contains the requested award', function () {
    $decree = Decree::factory()->create(['number' => '1131/2026', 'date' => '2026-11-23']);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => 'latest',
        'award' => 'орден Богдана Хмельницького',
    ])
        ->assertHasErrors()
        ->assertSee(['не відповідає', 'list_awards']);
});

it('lists every awardee of an award page by page', function () {
    foreach (range(1, 5) as $index) {
        Awardee::factory()->create(['award_id' => $this->order->getKey()]);
    }

    Awardee::factory()->create(['award_id' => $this->medal->getKey()]);

    AwardeeServer::tool(SearchAwardeesTool::class, ['award' => 'орден за мужність', 'limit' => 2, 'page' => 3])
        ->assertOk()
        ->assertSee('Показано: 1 (сторінка 3 із 3).')
        ->assertStructuredContentPath('total', 5)
        ->assertStructuredContentPath('returned', 1)
        ->assertStructuredContentPath('page', 3)
        ->assertStructuredContentPath('last_page', 3);
});

it('lists all awardees of a decree page by page', function () {
    $decree = Decree::factory()->create(['number' => '601/2025', 'date' => '2025-08-24']);

    Awardee::factory()->count(3)->create(['decree_id' => $decree->getKey()]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => '601/2025',
        'per_page' => 2,
        'page' => 2,
    ])
        ->assertOk()
        ->assertSee('Показано записи 3–3 (сторінка 2 із 2).')
        ->assertStructuredContentPath('page', 2)
        ->assertStructuredContentPath('last_page', 2)
        ->assertStructuredContentPath('total', 3);
});
