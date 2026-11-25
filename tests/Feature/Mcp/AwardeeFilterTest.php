<?php

use App\Mcp\Servers\AwardeeServer;
use App\Mcp\Tools\ListAwardsTool;
use App\Mcp\Tools\ListDecreeAwardeesTool;
use App\Mcp\Tools\SearchAwardeesTool;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use App\Services\AwardeeSearch;
use App\Services\AwardMerger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->orderTitle = "орденом \u{00AB}За мужність\u{00BB} ІІІ ступеня";
    $this->medalTitle = "медаллю \u{00AB}За військову службу Україні\u{00BB}";

    $this->order = Award::factory()->create(['name' => $this->orderTitle]);
    $this->medal = Award::factory()->create(['name' => $this->medalTitle]);
});

it('answers whether a decree contains awardees of a given award', function () {
    $decree = Decree::factory()->create([
        'number' => '1025/2026',
        'date' => '2026-10-01',
        'url' => decreeUrl('1025-2026'),
    ]);

    Awardee::factory()->count(2)->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->order->getKey(),
    ]);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => 'No 1025',
        'award' => 'орден за мужність',
    ])
        ->assertOk()
        ->assertSee(['нагороджених — 3', 'За фільтром'])
        ->assertStructuredContentPath('decree.total_awardees', 3)
        ->assertStructuredContentPath('total', 2)
        ->assertStructuredContentPath('filters.award', 'орден за мужність')
        ->assertStructuredContentPath('filters.matched_awards', [$this->orderTitle])
        ->assertStructuredContentCount(2, 'awardees');
});

it('reports zero awardees when the decree has none of the given award', function () {
    $decree = Decree::factory()->create(['number' => '1025/2026', 'date' => '2026-10-01']);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => '1025/2026',
        'award' => 'орден за мужність',
    ])
        ->assertOk()
        ->assertSee("За фільтром (нагорода: {$this->orderTitle}): 0.")
        ->assertStructuredContentPath('total', 0)
        ->assertStructuredContentPath('returned', 0)
        ->assertStructuredContentCount(0, 'awardees');
});

it('keeps decree totals for filtered listings without an extra unfiltered count query', function () {
    $decree = Decree::factory()->create(['number' => '1025/2026', 'date' => '2026-10-01']);

    Awardee::factory()->count(2)->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->order->getKey(),
        'is_posthumous' => true,
    ]);

    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
        'is_posthumous' => false,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => '1025/2026',
        'is_posthumous' => true,
    ])
        ->assertOk()
        ->assertStructuredContentPath('decree.total_awardees', 3)
        ->assertStructuredContentPath('total', 2);

    $unfilteredCountQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(function (string $query): bool {
            $sql = mb_strtolower($query, 'UTF-8');

            return str_contains($sql, 'count(*) as aggregate')
                && str_contains($sql, 'decree_id')
                && ! str_contains($sql, 'is_posthumous');
        });

    DB::disableQueryLog();

    expect($unfilteredCountQueries)->toHaveCount(0);
});

it('understands the degree written as a word or as a roman numeral', function (string $award) {
    $secondDegree = Award::factory()->create(['name' => "орденом \u{00AB}За мужність\u{00BB} ІІ ступеня"]);

    Awardee::factory()->create(['award_id' => $this->order->getKey()]);
    Awardee::factory()->create(['award_id' => $secondDegree->getKey()]);

    AwardeeServer::tool(SearchAwardeesTool::class, ['award' => $award])
        ->assertOk()
        ->assertStructuredContentPath('filters.matched_awards', [$this->orderTitle])
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('awardees.0.award', $this->orderTitle);
})->with([
    'degree as an ordinal word' => 'орден за мужність третього ступеня',
    'degree as a latin numeral' => 'орден «За мужність» III ступеня',
    'degree as an arabic digit' => 'орден за мужність 3 ступеня',
]);

it('understands the award title without quotes and in another case', function () {
    AwardeeServer::tool(SearchAwardeesTool::class, ['award' => 'медаль за військову службу'])
        ->assertOk()
        ->assertStructuredContentPath('filters.matched_awards', [$this->medalTitle]);
});

it('filters a person search by award and by posthumous status', function () {
    Awardee::factory()->create([
        'award_id' => $this->order->getKey(),
        'full_name' => 'Петренка Сергія Петровича',
        'full_name_nominative' => 'Петренко Сергій Петрович',
        'is_posthumous' => true,
    ]);

    Awardee::factory()->create([
        'award_id' => $this->medal->getKey(),
        'full_name' => 'Петренка Сергія Петровича',
        'full_name_nominative' => 'Петренко Сергій Петрович',
    ]);

    AwardeeServer::tool(SearchAwardeesTool::class, [
        'query' => 'Петренко Сергій',
        'award' => 'орден за мужність',
    ])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('awardees.0.award', $this->orderTitle);

    AwardeeServer::tool(SearchAwardeesTool::class, [
        'query' => 'Петренко Сергій',
        'is_posthumous' => true,
    ])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('filters.is_posthumous', true)
        ->assertStructuredContentPath('awardees.0.is_posthumous', true);
});

it('lists the awards with the number of award records', function () {
    Awardee::factory()->count(2)->create(['award_id' => $this->medal->getKey()]);

    AwardeeServer::tool(ListAwardsTool::class, ['query' => 'медаль'])
        ->assertOk()
        ->assertSee([$this->medalTitle, '| Назва нагороди | Нагороджені |'])
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('awards.0.name', $this->medalTitle)
        ->assertStructuredContentPath('awards.0.awardees_count', 2)
        ->assertStructuredContentPath('awards.0.posthumous_awardees_count', 0);
});

it('appends the posthumous part of the count like the awards table does', function () {
    Awardee::factory()->create(['award_id' => $this->medal->getKey(), 'is_posthumous' => true]);
    Awardee::factory()->create(['award_id' => $this->medal->getKey()]);

    AwardeeServer::tool(ListAwardsTool::class, ['query' => 'медаль'])
        ->assertOk()
        ->assertSee('| '.$this->medalTitle.' | 2 (1 посмертно) |')
        ->assertStructuredContentPath('awards.0.awardees_count', 2)
        ->assertStructuredContentPath('awards.0.posthumous_awardees_count', 1)
        ->assertStructuredContentPath('awards.0.awardees_label', '2 (1 посмертно)');
});

it('summarises every award of a decree in one call', function () {
    $decree = Decree::factory()->create(['number' => '1131/2026', 'date' => '2026-11-23']);
    $this->order->update(['sort' => 1]);
    $this->medal->update(['sort' => 2]);
    Awardee::factory()->count(2)->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->order->getKey(),
    ]);
    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->order->getKey(),
        'is_posthumous' => true,
    ]);
    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);
    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '1131/2026', 'per_page' => 1])
        ->assertOk()
        ->assertSee([
            'Нагороджені за нагородами',
            '| Назва нагороди | Нагороджені |',
            '|---|:---:|',
            "| {$this->orderTitle} | 3 (1 посмертно) |",
            "| {$this->medalTitle} | 1 |",
        ])
        ->assertStructuredContentCount(2, 'awards')
        ->assertStructuredContentPath('awards.0.name', $this->orderTitle)
        ->assertStructuredContentPath('awards.0.awardees_count', 3)
        ->assertStructuredContentPath('awards.0.posthumous_awardees_count', 1)
        ->assertStructuredContentPath('awards.0.awardees_label', '3 (1 посмертно)')
        ->assertStructuredContentPath('awards.1.name', $this->medalTitle)
        ->assertStructuredContentPath('awards.1.posthumous_awardees_count', 0)
        ->assertStructuredContentPath('awards.1.awardees_label', '1');
});
it('renders the award summary on the first page only but keeps it in every page of structured content', function () {
    $decree = Decree::factory()->create(['number' => '601/2025', 'date' => '2025-08-24']);
    Awardee::factory()->count(3)->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);
    AwardeeServer::tool(ListDecreeAwardeesTool::class, ['decree_number' => '601/2025', 'per_page' => 2, 'page' => 2])
        ->assertOk()
        ->assertDontSee('Нагороджені за нагородами')
        ->assertStructuredContentCount(1, 'awards')
        ->assertStructuredContentPath('awards.0.awardees_count', 3);
});
it('omits the award summary when the decree list is narrowed by an award', function () {
    $decree = Decree::factory()->create(['number' => '601/2025', 'date' => '2025-08-24']);
    Awardee::factory()->create([
        'decree_id' => $decree->getKey(),
        'award_id' => $this->medal->getKey(),
    ]);
    AwardeeServer::tool(ListDecreeAwardeesTool::class, [
        'decree_number' => '601/2025',
        'award' => 'медаль за військову службу',
    ])
        ->assertOk()
        ->assertDontSee('Нагороджені за нагородами')
        ->assertSee("За фільтром (нагорода: {$this->medalTitle}): 1.")
        ->assertStructuredContentCount(0, 'awards');
});
it('rejects an award phrase that matches no title and points to the awards list', function () {
    AwardeeServer::tool(SearchAwardeesTool::class, ['award' => 'орден невідомий'])
        ->assertHasErrors()
        ->assertSee(['не відповідає', 'list_awards']);
});

it('requires at least one search criterion', function () {
    AwardeeServer::tool(SearchAwardeesTool::class, [])
        ->assertHasErrors()
        ->assertSee('Вкажіть хоча б один критерій');
});

it('caches the awards collection so a second call skips the database query', function () {
    Awardee::factory()->count(2)->create(['award_id' => $this->order->getKey()]);

    // Перший виклик прогріває кеш.
    AwardeeServer::tool(ListAwardsTool::class, [])
        ->assertOk()
        ->assertStructuredContentPath('awards.0.awardees_count', 2);

    expect(Cache::has(AwardeeSearch::AWARDS_CACHE_KEY))->toBeTrue();

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        // Другий виклик бере дані з кешу без звернення до SQLite.
        AwardeeServer::tool(ListAwardsTool::class, [])
            ->assertOk()
            ->assertStructuredContentPath('awards.0.awardees_count', 2);

        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($queries)->toBeEmpty();
});

it('busts the awards cache after a merge so the tool returns fresh counts', function () {
    Awardee::factory()->count(3)->create(['award_id' => $this->order->getKey()]);
    Awardee::factory()->create(['award_id' => $this->medal->getKey()]);

    // Warm the cache.
    AwardeeServer::tool(ListAwardsTool::class, [])
        ->assertOk()
        ->assertStructuredContentPath('total', 2);

    expect(Cache::has(AwardeeSearch::AWARDS_CACHE_KEY))->toBeTrue();

    // Merging the two awards invalidates the cache.
    app(AwardMerger::class)->merge(
        Collection::make([$this->order, $this->medal]),
    );

    expect(Cache::has(AwardeeSearch::AWARDS_CACHE_KEY))->toBeFalse();

    // The next call rebuilds the cache with the merged counts.
    AwardeeServer::tool(ListAwardsTool::class, [])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('awards.0.awardees_count', 4);

    expect(Cache::has(AwardeeSearch::AWARDS_CACHE_KEY))->toBeTrue();
});

it('caches the awards together with their awardee counts', function () {
    Awardee::factory()->create(['award_id' => $this->order->getKey()]);

    AwardeeServer::tool(ListAwardsTool::class, [])
        ->assertOk();

    $cachedAwards = Cache::get(AwardeeSearch::AWARDS_CACHE_KEY);

    expect($cachedAwards)->toBeInstanceOf(Collection::class)
        ->and($cachedAwards->firstWhere('name', $this->orderTitle)?->awardees_count)->toBe(1)
        ->and($cachedAwards->firstWhere('name', $this->orderTitle)?->posthumous_awardees_count)->toBe(0)
        ->and($cachedAwards->firstWhere('name', $this->medalTitle)?->awardees_count)->toBe(0);
});
