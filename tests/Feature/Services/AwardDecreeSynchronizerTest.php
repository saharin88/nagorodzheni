<?php

use App\Contracts\AwardDecreeListParser;
use App\Contracts\AwardDecreeSynchronizer;
use App\Contracts\DecreeAwardeeParser;
use App\Exceptions\DecreeParseException;
use App\Models\Decree;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('stores the new decrees of the list together with their awardees', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    Http::fake([decreeUrl('8752026-61465') => Http::response(decreeFixture('875_2026.html'), 200)]);

    $requestedSearches = [];

    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->twice()
        ->with(Mockery::on(function (string $url) use (&$requestedSearches): bool {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $requestedSearches[] = $query['s-text'] ?? '';

            return str_starts_with($url, 'https://www.president.gov.ua/documents/decrees?')
                && ($query['contain-rule'] ?? '') === 'contains';
        }))
        ->andReturn([
            [
                'number' => '875/2026',
                'url' => decreeUrl('8752026-61465'),
            ],
        ]);

    expect(app(AwardDecreeSynchronizer::class)->sync())
        ->toBe(['added' => 1, 'awardees' => 174, 'skipped' => 0]);

    // A decree that both searches list is stored once.
    expect($requestedSearches)->toBe([
        __('the search text of the decree list about state awards', locale: 'uk'),
        __('the search text of the decree list about the Hero of Ukraine title', locale: 'uk'),
    ]);

    $decree = Decree::query()->sole();

    expect($decree->number)->toBe('875/2026')
        ->and($decree->date->toDateString())->toBe('2026-09-04')
        ->and($decree->url)->toBe(decreeUrl('8752026-61465'));

    $this->assertDatabaseCount('awardees', 174);

    Http::assertSentCount(1);
});

it('stores the decrees of both searches', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    Http::fake([
        decreeUrl('2642022-42217') => Http::response(decreeFixture('264_2022.html'), 200),
        decreeUrl('8752026-61465') => Http::response(decreeFixture('875_2026.html'), 200),
    ]);

    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->twice()
        ->andReturnUsing(function (string $url): array {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return ($query['s-text'] ?? '') === __('the search text of the decree list about the Hero of Ukraine title', locale: 'uk')
                ? [['number' => '264/2022', 'url' => decreeUrl('2642022-42217')]]
                : [['number' => '875/2026', 'url' => decreeUrl('8752026-61465')]];
        });

    expect(app(AwardDecreeSynchronizer::class)->sync())
        ->toBe(['added' => 2, 'awardees' => 179, 'skipped' => 0]);

    expect(Decree::query()->pluck('number')->sort()->values()->all())->toBe(['264/2022', '875/2026']);

    $this->assertDatabaseCount('awardees', 179);
});

it('stores nothing when every decree of the list is stored already', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    Decree::factory()->create([
        'number' => '875/2026',
        'url' => decreeUrl('8752026-61465'),
    ]);

    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->twice()
        ->andReturn([
            [
                'number' => '875/2026',
                'url' => decreeUrl('8752026-61465'),
            ],
        ]);

    expect(app(AwardDecreeSynchronizer::class)->sync())
        ->toBe(['added' => 0, 'awardees' => 0, 'skipped' => 0]);

    Http::assertNothingSent();

    $this->assertDatabaseCount('decrees', 1);
    $this->assertDatabaseCount('awardees', 0);
});

it('rejects the list that carries no decrees at all', function () {
    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->once()
        ->andReturn([]);

    expect(fn () => app(AwardDecreeSynchronizer::class)->sync())
        ->toThrow(DecreeParseException::class);

    $this->assertDatabaseCount('decrees', 0);
});

it('keeps importing the remaining decrees when one of them cannot be parsed', function () {
    Storage::fake('local');
    Http::preventStrayRequests();

    Http::fake([
        decreeUrl('8972026-61565') => Http::response('', 200),
        decreeUrl('8752026-61465') => Http::response(decreeFixture('875_2026.html'), 200),
    ]);

    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->twice()
        ->andReturn([
            [
                'number' => '897/2026',
                'url' => decreeUrl('8972026-61565'),
            ],
            [
                'number' => '875/2026',
                'url' => decreeUrl('8752026-61465'),
            ],
        ]);

    expect(app(AwardDecreeSynchronizer::class)->sync())
        ->toBe(['added' => 1, 'awardees' => 174, 'skipped' => 1]);

    expect(Decree::query()->pluck('number')->all())->toBe(['875/2026']);
});

it('removes the decree it has just stored when its awardees cannot be imported', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Log::spy();

    Http::fake([decreeUrl('8752026-61465') => Http::response(decreeFixture('875_2026.html'), 200)]);

    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->twice()
        ->andReturn([
            [
                'number' => '875/2026',
                'url' => decreeUrl('8752026-61465'),
            ],
        ]);

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->once()
        ->andThrow(new DecreeParseException('Unable to parse decree awardees'));

    expect(app(AwardDecreeSynchronizer::class)->sync())
        ->toBe(['added' => 0, 'awardees' => 0, 'skipped' => 1]);

    $this->assertDatabaseCount('decrees', 0);
    $this->assertDatabaseCount('awardees', 0);

    Log::shouldHaveReceived('error')
        ->once()
        ->with(Mockery::type('string'), Mockery::type('array'));
});
