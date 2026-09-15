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

    $this->mock(AwardDecreeListParser::class)
        ->shouldReceive('getDecrees')
        ->once()
        ->with(Mockery::on(function (string $url): bool {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return str_starts_with($url, 'https://www.president.gov.ua/documents/decrees?')
                && $query['contain-rule'] === 'contains'
                && $query['s-text'] === __('the search text of the decree list about state awards', locale: 'uk');
        }))
        ->andReturn([
            [
                'number' => '875/2026',
                'url' => decreeUrl('8752026-61465'),
            ],
        ]);

    expect(app(AwardDecreeSynchronizer::class)->sync())
        ->toBe(['added' => 1, 'awardees' => 174, 'skipped' => 0]);

    $decree = Decree::query()->sole();

    expect($decree->number)->toBe('875/2026')
        ->and($decree->date->toDateString())->toBe('2026-09-04')
        ->and($decree->url)->toBe(decreeUrl('8752026-61465'));

    $this->assertDatabaseCount('awardees', 174);

    Http::assertSentCount(1);
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
        ->once()
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
        ->once()
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
        ->once()
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
