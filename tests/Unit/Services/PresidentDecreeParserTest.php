<?php

use App\Contracts\DecreeAwardeeParser;
use App\Contracts\DecreeHtmlFetcher;
use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Serve the given decree page through the fetcher contract, so that the parser
 * tests never touch the network or the disk cache of CachedPresidentDecreeFetcher.
 */
function fakeDecreeHtml(string $html): void
{
    $fetcher = Mockery::mock(DecreeHtmlFetcher::class);
    $fetcher->shouldReceive('fetchHtml')->andReturn($html);

    app()->instance(DecreeHtmlFetcher::class, $fetcher);
}

/**
 * Replace every match of the pattern in the given decree fixture.
 */
function decreeFixtureWithout(string $fileName, string $pattern, string $replacement): string
{
    $html = preg_replace($pattern, $replacement, decreeFixture($fileName));

    expect($html)->not->toBeNull();

    return $html;
}

it('parses the decree number and date from the decree page', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixture('875_2026.html'));

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeDate($url))->toBeInstanceOf(CarbonImmutable::class)
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04');
});

it('parses the meta of the decree whose rank is separated by a hyphen', function () {
    $url = decreeUrl('3712021-39725');

    fakeDecreeHtml(decreeFixture('371_2021.html'));

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('371/2021')
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2021-05-18');
});

it('falls back to the page title when the decree heading is missing', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixtureWithout('875_2026.html', '/<h1 itemprop="name">.*?<\/h1>/su', ''));

    expect(app(DecreeMetaParser::class)->getDecreeNumber($url))->toBe('875/2026');
});

it('recognises an award decree when only its short description mentions the awards', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixtureWithout(
        '875_2026.html',
        '/<meta (?:name="(?:description|twitter:description)"|property="og:description")[^>]*>/u',
        ''
    ));

    expect(app(DecreeMetaParser::class)->getDecreeNumber($url))->toBe('875/2026');
});

it('asks the fetcher only once per decree while the meta is parsed', function () {
    $url = decreeUrl('8752026-61465');

    $fetcher = Mockery::mock(DecreeHtmlFetcher::class);
    $fetcher->shouldReceive('fetchHtml')->once()->with($url)->andReturn(decreeFixture('875_2026.html'));

    app()->instance(DecreeHtmlFetcher::class, $fetcher);

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeNumber($url))->toBe('875/2026')
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04');
});

it('asks the fetcher only once per decree while the awardees are parsed', function () {
    $url = decreeUrl('8752026-61465');

    $fetcher = Mockery::mock(DecreeHtmlFetcher::class);
    $fetcher->shouldReceive('fetchHtml')->once()->with($url)->andReturn(decreeFixture('875_2026.html'));

    app()->instance(DecreeHtmlFetcher::class, $fetcher);

    $parser = app(DecreeAwardeeParser::class);

    expect($parser->getAwardees($url))->toHaveCount(174)
        ->and($parser->getAwardees($url))->toHaveCount(174);
});

it('hands the decree url to the fetcher exactly as it was given', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465?fbclid=abc';

    $fetcher = Mockery::mock(DecreeHtmlFetcher::class);
    $fetcher->shouldReceive('fetchHtml')->once()->with($url)->andReturn(decreeFixture('875_2026.html'));

    app()->instance(DecreeHtmlFetcher::class, $fetcher);

    expect(app(DecreeMetaParser::class)->getDecreeNumber($url))->toBe('875/2026');
});

it('parses every awardee mentioned in the decree', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixture('875_2026.html'));

    $awardees = app(DecreeAwardeeParser::class)->getAwardees($url);

    expect($awardees)->toHaveCount(174)
        ->and($awardees[0])->toBe([
            'full_name' => 'Вовченка Олександра Євгеновича',
            'rank' => 'молодшого лейтенанта',
            'award' => 'відзнакою Президента України «Хрест бойових заслуг»',
            'is_posthumous' => false,
        ])
        ->and($awardees[173])->toBe([
            'full_name' => 'Ясніковського Олега Михайловича',
            'rank' => 'старшого лейтенанта медичної служби',
            'award' => 'медаллю «За врятоване життя»',
            'is_posthumous' => false,
        ])
        ->and(collect($awardees)->where('is_posthumous', true))->toHaveCount(107)
        ->and(collect($awardees)->pluck('award')->unique())->toHaveCount(11);
});

it('marks the awardees that were honoured posthumously', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixture('875_2026.html'));

    $awardees = collect(app(DecreeAwardeeParser::class)->getAwardees($url));

    expect($awardees->firstWhere('full_name', 'Сидора Юрія Васильовича'))->toBe([
        'full_name' => 'Сидора Юрія Васильовича',
        'rank' => 'капітана',
        'award' => 'орденом Богдана Хмельницького ІІ ступеня',
        'is_posthumous' => true,
    ]);
});

it('parses the awardees when the rank is separated by a hyphen instead of a dash', function () {
    $url = decreeUrl('3712021-39725');

    fakeDecreeHtml(decreeFixture('371_2021.html'));

    expect(app(DecreeAwardeeParser::class)->getAwardees($url))->toBe([
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
            'award' => 'медаллю «Захиснику Вітчизни»',
            'is_posthumous' => false,
        ],
    ]);
});

it('normalizes the award names of a decree that writes them in another quote style', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixtureWithout('875_2026.html', '/[“”]/u', '"'));

    $awardNames = collect(app(DecreeAwardeeParser::class)->getAwardees($url))->pluck('award')->unique();

    expect($awardNames)->toHaveCount(11)
        ->and($awardNames->filter(fn (string $award): bool => preg_match('/["“”]/u', $award) === 1))->toBeEmpty()
        ->and($awardNames)->toContain('відзнакою Президента України «Хрест бойових заслуг»')
        ->and($awardNames)->toContain('медаллю «За врятоване життя»');
});

it('throws an exception when the decree is not about state awards', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(str_replace(
        'Про відзначення державними нагородами України',
        'Про внесення змін до деяких указів Президента України',
        decreeFixture('875_2026.html')
    ));

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Указ не стосується державних нагород');
});

it('throws an exception when the fetched page is empty', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml('');

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Указ не стосується державних нагород');
});

it('throws an exception when the decree number cannot be parsed', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixtureWithout('875_2026.html', '/№\s*[0-9]+\/[0-9]{4}/u', 'без номера'));

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Не вдалося розібрати номер указу');
});

it('throws an exception when the decree date is missing in the article body', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(decreeFixtureWithout(
        '875_2026.html',
        '/\b\d{1,2}\s+[а-яіїєґ]+\s+\d{4}\s+року\b/ui',
        'дата відсутня'
    ));

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Не вдалося розібрати дату указу');
});

it('throws an exception when the decree date has an unknown month', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(str_replace('вересня 2026 року', 'місяця 2026 року', decreeFixture('875_2026.html')));

    expect(fn () => app(DecreeMetaParser::class)->getDecreeNumber($url))
        ->toThrow(DecreeParseException::class, 'Невідомий український місяць');
});

it('throws an exception when the article body of the decree is missing', function () {
    $url = decreeUrl('8752026-61465');

    fakeDecreeHtml(str_replace('itemprop="articleBody"', '', decreeFixture('875_2026.html')));

    expect(fn () => app(DecreeAwardeeParser::class)->getAwardees($url))
        ->toThrow(DecreeParseException::class, 'Не вдалося розібрати нагороджених');
});
