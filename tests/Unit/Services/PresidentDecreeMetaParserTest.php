<?php

use App\Contracts\Contracts\DecreeAwardeeParser;
use App\Contracts\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The decree paths that are exercised by this file. Every one of them is faked,
 * so the tests never touch the network and stay independent from each other.
 *
 * @return list<string>
 */
function decreePaths(): array
{
    return [
        '8752026-61465',
        '3712021-39725',
        '8752026-61465-unparseable',
        '8752026-61465-no-date',
        '8752026-61465-unknown-month',
        '8752026-61465-not-award',
        '8752026-61465-not-found',
        '8752026-61465-connection-error',
    ];
}

function decreeUrl(string $path): string
{
    return "https://www.president.gov.ua/documents/{$path}";
}

function decreeFixture(string $fileName): string
{
    $html = file_get_contents(base_path("tests/Fixtures/decrees/{$fileName}"));

    expect($html)->not->toBeFalse();

    return $html;
}

function decreeHtmlCachePath(string $decreeUrl): string
{
    return storage_path('framework/cache/decrees/'.hash('sha256', $decreeUrl).'.html');
}

function forgetDecreeHtmlCache(string $decreeUrl): void
{
    $path = decreeHtmlCachePath($decreeUrl);

    if (file_exists($path)) {
        unlink($path);
    }
}

beforeEach(function (): void {
    foreach (decreePaths() as $path) {
        forgetDecreeHtmlCache(decreeUrl($path));
    }
});

afterEach(function (): void {
    foreach (decreePaths() as $path) {
        forgetDecreeHtmlCache(decreeUrl($path));
    }
});

it('parses the decree number and date from the decree page', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeDate($url))->toBeInstanceOf(CarbonImmutable::class)
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04');
});

it('falls back to the page title when the decree heading is missing', function () {
    $url = decreeUrl('8752026-61465');

    $html = preg_replace(
        '/<h1 itemprop="name">.*?<\/h1>/su',
        '',
        decreeFixture('875_2026.html')
    );

    expect($html)->not->toBeNull();

    Http::fake([$url => Http::response($html, 200)]);

    expect(app(DecreeMetaParser::class)->getDecreeNumber($url))->toBe('875/2026');
});

it('parses the meta from the html that is already cached on disk', function () {
    $url = decreeUrl('8752026-61465');

    File::ensureDirectoryExists(dirname(decreeHtmlCachePath($url)));
    File::put(decreeHtmlCachePath($url), decreeFixture('875_2026.html'));

    Http::fake();

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04');

    Http::assertNothingSent();
});

it('caches the fetched html on disk and asks the site only once per url', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Http::fake([$url => Http::response($html, 200)]);

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04')
        ->and($parser->getDecreeNumber($url))->toBe('875/2026');

    expect(decreeHtmlCachePath($url))->toBeFile()
        ->and(file_get_contents(decreeHtmlCachePath($url)))->toBe($html);

    Http::assertSentCount(1);

    Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent')
        && $request->hasHeader('Accept-Language')
        && $request->hasHeader('Sec-Ch-Ua')
        && $request->hasHeader('Sec-Fetch-Mode'));
});

it('does not cache the html when the decree response status is not 200', function () {
    $url = decreeUrl('8752026-61465-not-found');

    Http::fake([$url => Http::response('No Content', 204)]);

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Unexpected decree response status');

    expect(decreeHtmlCachePath($url))->not->toBeFile();
});

it('does not cache the html when the decree number cannot be parsed', function () {
    $url = decreeUrl('8752026-61465-unparseable');

    $html = preg_replace(
        '/№\s*[0-9]+\/[0-9]{4}/u',
        'без номера',
        decreeFixture('875_2026.html')
    );

    expect($html)->not->toBeNull();

    Http::fake([$url => Http::response($html, 200)]);

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Unable to parse decree number');

    expect(decreeHtmlCachePath($url))->not->toBeFile();
});

it('throws an exception when the decree date is missing in the article body', function () {
    $url = decreeUrl('8752026-61465-no-date');

    $html = preg_replace(
        '/\b\d{1,2}\s+[а-яіїєґ]+\s+\d{4}\s+року\b/ui',
        'дата відсутня',
        decreeFixture('875_2026.html')
    );

    expect($html)->not->toBeNull();

    Http::fake([$url => Http::response($html, 200)]);

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Unable to parse decree date');
});

it('throws an exception when the decree date has an unknown month', function () {
    $url = decreeUrl('8752026-61465-unknown-month');

    $html = str_replace('вересня 2026 року', 'місяця 2026 року', decreeFixture('875_2026.html'));

    Http::fake([$url => Http::response($html, 200)]);

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Unknown Ukrainian month');
});

it('recognises an award decree when only its short description mentions the awards', function () {
    $url = decreeUrl('8752026-61465');

    $html = preg_replace(
        '/<meta (?:name="(?:description|twitter:description)"|property="og:description")[^>]*>/u',
        '',
        decreeFixture('875_2026.html')
    );

    expect($html)->not->toBeNull();

    Http::fake([$url => Http::response($html, 200)]);

    expect(app(DecreeMetaParser::class)->getDecreeNumber($url))->toBe('875/2026');
});

it('throws an exception when the decree is not about state awards', function () {
    $url = decreeUrl('8752026-61465-not-award');

    $html = str_replace(
        'Про відзначення державними нагородами України',
        'Про внесення змін до деяких указів Президента України',
        decreeFixture('875_2026.html')
    );

    Http::fake([$url => Http::response($html, 200)]);

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Decree is not about state awards');
});

it('returns the meta even when the html cache file cannot be written', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    File::partialMock()
        ->shouldReceive('exists')
        ->once()
        ->andReturnFalse();
    File::partialMock()
        ->shouldReceive('ensureDirectoryExists')
        ->once();
    File::partialMock()
        ->shouldReceive('put')
        ->once()
        ->andThrow(new ErrorException('Permission denied'));

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04');
});

it('reports a connection error when the decree cannot be fetched', function () {
    $url = decreeUrl('8752026-61465-connection-error');

    Http::fake(fn (): never => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Connection error while fetching the decree');
});

it('rejects a decree url that is not using https', function () {
    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber('http://www.president.gov.ua/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Only HTTPS scheme is allowed');
});

it('rejects a decree url of a foreign host', function () {
    expect(fn () => app(DecreeMetaParser::class)->getDecreeDate('https://example.com/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Invalid decree URL host');
});

it('rejects a decree url that only contains the allowed host', function () {
    expect(fn () => app(DecreeAwardeeParser::class)->getAwardees('https://president.gov.ua.example.com/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Invalid decree URL host');
});

it('parses every awardee mentioned in the decree', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    $awardees = app(DecreeAwardeeParser::class)->getAwardees($url);

    expect($awardees)->toHaveCount(174)
        ->and($awardees[0])->toBe([
            'full_name' => 'Вовченка Олександра Євгеновича',
            'rank' => 'молодшого лейтенанта',
            'award' => 'відзнакою Президента України “Хрест бойових заслуг”',
            'is_posthumous' => false,
        ])
        ->and($awardees[173])->toBe([
            'full_name' => 'Ясніковського Олега Михайловича',
            'rank' => 'старшого лейтенанта медичної служби',
            'award' => 'медаллю “За врятоване життя”',
            'is_posthumous' => false,
        ])
        ->and(collect($awardees)->where('is_posthumous', true))->toHaveCount(107)
        ->and(collect($awardees)->pluck('award')->unique())->toHaveCount(11);
});

it('marks the awardees that were honoured posthumously', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    $awardees = collect(app(DecreeAwardeeParser::class)->getAwardees($url));

    expect($awardees->firstWhere('full_name', 'Сидора Юрія Васильовича'))->toBe([
        'full_name' => 'Сидора Юрія Васильовича',
        'rank' => 'капітана',
        'award' => 'орденом Богдана Хмельницького ІІ ступеня',
        'is_posthumous' => true,
    ]);
});

it('reuses the parsed awardees of a decree without asking the site again', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    $parser = app(DecreeAwardeeParser::class);

    expect($parser->getAwardees($url))->toBe($parser->getAwardees($url));

    Http::assertSentCount(1);
});

it('parses the awardees when the rank is separated by a hyphen instead of a dash', function () {
    $url = decreeUrl('3712021-39725');

    Http::fake([$url => Http::response(decreeFixture('371_2021.html'), 200)]);

    $awardees = app(DecreeAwardeeParser::class)->getAwardees($url);

    expect($awardees)->toBe([
        [
            'full_name' => 'Бродовського Богдана Віталійовича',
            'rank' => 'майора',
            'award' => 'орденом Богдана Хмельницького III ступеня',
            'is_posthumous' => true,
        ],
        [
            'full_name' => 'Костенко-Сидоренка Юрія Петровича',
            'rank' => 'капітана',
            'award' => 'орденом Богдана Хмельницького III ступеня',
            'is_posthumous' => false,
        ],
        [
            'full_name' => 'Шартаву Давіда',
            'rank' => 'старшого солдата',
            'award' => 'орденом Богдана Хмельницького III ступеня',
            'is_posthumous' => false,
        ],
        [
            'full_name' => 'Коваленка Петра Івановича',
            'rank' => 'полковника',
            'award' => 'звання Герой України',
            'is_posthumous' => true,
        ],
        [
            'full_name' => 'Шапаренка Артура Юрійовича',
            'rank' => 'солдата',
            'award' => 'медаллю “Захиснику Вітчизни”',
            'is_posthumous' => false,
        ],
    ]);
});

it('parses the meta of the decree whose rank is separated by a hyphen', function () {
    $url = decreeUrl('3712021-39725');

    Http::fake([$url => Http::response(decreeFixture('371_2021.html'), 200)]);

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('371/2021')
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2021-05-18');
});
