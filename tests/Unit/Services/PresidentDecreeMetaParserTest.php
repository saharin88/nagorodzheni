<?php

use App\Contracts\Contracts\DecreeAwardeeParser;
use App\Contracts\Contracts\DecreeMetaParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('parses decree number and date from decree page HTML', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    Cache::flush();

    Http::fake([
        $url => Http::response($html, 200),
    ]);

    $parser = app(DecreeMetaParser::class);

    expect($parser->getDecreeNumber($url))->toBe('875/2026');
    expect($parser->getDecreeDate($url))
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($parser->getDecreeDate($url)->toDateString())->toBe('2026-09-04');
});

it('caches decree HTML and parsed meta by decree URL', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    Cache::flush();
    $suffix = decreeCacheToken($url);
    $cachedHtmlPath = storage_path("framework/cache/decrees/{$suffix}.html");
    if (file_exists($cachedHtmlPath)) {
        unlink($cachedHtmlPath);
    }

    Http::fake([
        $url => Http::response($html, 200),
    ]);

    $parser = app(DecreeMetaParser::class);

    $parser->getDecreeNumber($url);
    $parser->getDecreeDate($url);
    $parser->getDecreeNumber($url);

    expect(file_exists($cachedHtmlPath))->toBeTrue()
        ->and(Cache::has("decree-meta:parsed:{$suffix}"))->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent')
        && $request->hasHeader('Accept-Language')
        && $request->hasHeader('Sec-Ch-Ua')
        && $request->hasHeader('Sec-Fetch-Mode'));
    Http::assertSentCount(1);
});

it('does not cache decree HTML when response status is not 200', function () {
    $url = 'https://www.president.gov.ua/documents/non-200-decree';

    Cache::flush();
    $cachedHtmlPath = storage_path('framework/cache/decrees/'.decreeCacheToken($url).'.html');
    if (file_exists($cachedHtmlPath)) {
        unlink($cachedHtmlPath);
    }

    Http::fake([
        $url => Http::response('No Content', 204),
    ]);

    $parser = app(DecreeMetaParser::class);

    expect(fn () => $parser->getDecreeNumber($url))
        ->toThrow(RuntimeException::class, 'Unexpected decree response status');

    expect(file_exists($cachedHtmlPath))->toBeFalse();
});

it('does not cache decree HTML when decree number/date are not parseable', function () {
    $url = 'https://www.president.gov.ua/documents/invalid-decree-html';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    $invalidHtml = preg_replace('/№\s*[0-9]+\/[0-9]{4}/u', 'без номера', $html);

    expect($invalidHtml)->not->toBeNull();

    Cache::flush();
    $cachedHtmlPath = storage_path('framework/cache/decrees/'.decreeCacheToken($url).'.html');
    if (file_exists($cachedHtmlPath)) {
        unlink($cachedHtmlPath);
    }

    Http::fake([
        $url => Http::response($invalidHtml, 200),
    ]);

    $parser = app(DecreeMetaParser::class);

    expect(fn () => $parser->getDecreeNumber($url))
        ->toThrow(RuntimeException::class, 'Unable to parse decree number');

    expect(file_exists($cachedHtmlPath))->toBeFalse();
});

it('throws exception when decree date is missing in article body', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465-missing-date';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    $htmlWithoutDate = preg_replace('/\b\d{1,2}\s+[а-яіїєґ]+\s+\d{4}\s+року\b/ui', 'дата відсутня', $html);

    expect($htmlWithoutDate)->not->toBeNull();

    Cache::flush();

    Http::fake([
        $url => Http::response($htmlWithoutDate, 200),
    ]);

    $parser = app(DecreeMetaParser::class);

    expect(fn () => $parser->getDecreeNumber($url))
        ->toThrow(RuntimeException::class, 'Unable to parse decree date');
});

it('returns decree meta even when html cache file cannot be written', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    Cache::flush();

    Http::fake([
        $url => Http::response($html, 200),
    ]);

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

it('throws exception when decree is not about state awards', function () {
    $url = 'https://www.president.gov.ua/documents/not-award-decree';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    $notAwardHtml = preg_replace(
        '/Про відзначення державними нагородами України/u',
        'Про внесення змін до деяких указів Президента України',
        $html
    );

    expect($notAwardHtml)->not->toBeNull();

    Cache::flush();

    Http::fake([
        $url => Http::response($notAwardHtml, 200),
    ]);

    $parser = app(DecreeMetaParser::class);

    expect(fn () => $parser->getDecreeNumber($url))
        ->toThrow(RuntimeException::class, 'Decree is not about state awards');
});

it('parses every awardee mentioned in the decree', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    Cache::flush();
    $cachedHtmlPath = storage_path('framework/cache/decrees/'.decreeCacheToken($url).'.html');
    if (file_exists($cachedHtmlPath)) {
        unlink($cachedHtmlPath);
    }

    Http::fake([
        $url => Http::response($html, 200),
    ]);

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
    $url = 'https://www.president.gov.ua/documents/8752026-61465';
    $html = file_get_contents(base_path('№875_2026.htm'));

    expect($html)->not->toBeFalse();

    Cache::flush();
    $cachedHtmlPath = storage_path('framework/cache/decrees/'.decreeCacheToken($url).'.html');
    if (file_exists($cachedHtmlPath)) {
        unlink($cachedHtmlPath);
    }

    Http::fake([
        $url => Http::response($html, 200),
    ]);

    $awardees = collect(app(DecreeAwardeeParser::class)->getAwardees($url));

    expect($awardees->firstWhere('full_name', 'Сидора Юрія Васильовича'))->toBe([
        'full_name' => 'Сидора Юрія Васильовича',
        'rank' => 'капітана',
        'award' => 'орденом Богдана Хмельницького ІІ ступеня',
        'is_posthumous' => true,
    ]);
});

it('parses awardees when the rank is separated by a hyphen instead of a dash', function () {
    $url = 'https://www.president.gov.ua/documents/3712021-39725';
    $html = file_get_contents(base_path('№371_2021.html'));

    expect($html)->not->toBeFalse();

    Cache::flush();
    $cachedHtmlPath = storage_path('framework/cache/decrees/'.decreeCacheToken($url).'.html');
    if (file_exists($cachedHtmlPath)) {
        unlink($cachedHtmlPath);
    }

    Http::fake([
        $url => Http::response($html, 200),
    ]);

    $awardees = app(DecreeAwardeeParser::class)->getAwardees($url);

    expect($awardees)->toHaveCount(22)
        ->and($awardees[0])->toBe([
            'full_name' => 'Бродовського Богдана Віталійовича',
            'rank' => 'майора',
            'award' => 'орденом Богдана Хмельницького III ступеня',
            'is_posthumous' => true,
        ])
        ->and($awardees[6])->toBe([
            'full_name' => 'Шартаву Давіда',
            'rank' => 'старшого солдата',
            'award' => 'орденом “За мужність” III ступеня',
            'is_posthumous' => true,
        ])
        ->and($awardees[21])->toBe([
            'full_name' => 'Шапаренка Артура Юрійовича',
            'rank' => 'солдата',
            'award' => 'медаллю “Захиснику Вітчизни”',
            'is_posthumous' => false,
        ])
        ->and(collect($awardees)->where('is_posthumous', true))->toHaveCount(7)
        ->and(collect($awardees)->pluck('award')->unique())->toHaveCount(4);
});

function decreeCacheToken(string $decreeUrl): string
{
    return hash('sha256', $decreeUrl);
}
