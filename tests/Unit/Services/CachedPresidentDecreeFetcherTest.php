<?php

use App\Contracts\DecreeHtmlFetcher;
use App\Exceptions\DecreeParseException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Sleep::fake();
});

/**
 * The cache file that the fetcher derives from the decree number and the year in the URL.
 */
function decreeCachePath(string $number, string $year): string
{
    return "decrees/{$year}/{$number}-{$year}.html";
}

it('returns the decree html and caches it next to the decree number and year', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Http::fake([$url => Http::response($html, 200)]);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    Storage::disk('local')->assertExists(decreeCachePath('875', '2026'));
    expect(Storage::disk('local')->get(decreeCachePath('875', '2026')))->toBe($html);

    Http::assertSentCount(1);
});

it('serves the decree from the cache on disk when it was fetched before', function () {
    $url = decreeUrl('3712021-39725');
    $html = decreeFixture('371_2021.html');

    Http::fake([$url => Http::response($html, 200)]);

    $fetcher = app(DecreeHtmlFetcher::class);

    expect($fetcher->fetchHtml($url))->toBe($html)
        ->and($fetcher->fetchHtml($url))->toBe($html);

    Http::assertSentCount(1);
});

it('keeps the cached decree html across fetcher instances', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Http::fake([$url => Http::response($html, 200)]);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    $this->app->forgetInstance(DecreeHtmlFetcher::class);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    Http::assertSentCount(1);
});

it('reads the decree html from the disk without asking the site again', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Storage::disk('local')->put(decreeCachePath('875', '2026'), $html);

    Http::fake();

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    Http::assertNothingSent();
});

it('serves the decree html from the site when it is fetched fresh', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Storage::disk('local')->put(decreeCachePath('875', '2026'), '<html lang="ua"><body>Cached page</body></html>');
    Http::fake([$url => Http::response($html, 200)]);

    expect(app(DecreeHtmlFetcher::class)->fetchFreshHtml($url))->toBe($html);

    Http::assertSentCount(1);
    expect(Storage::disk('local')->get(decreeCachePath('875', '2026')))->toBe('<html lang="ua"><body>Cached page</body></html>');
});

it('keeps no cache file for the page that is fetched fresh', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    app(DecreeHtmlFetcher::class)->fetchFreshHtml($url);

    Storage::disk('local')->assertMissing(decreeCachePath('875', '2026'));
});

it('reuses the cached decree html when the same decree is requested through another subdomain', function () {
    $html = decreeFixture('875_2026.html');

    Storage::disk('local')->put(decreeCachePath('875', '2026'), $html);

    Http::fake();

    expect(app(DecreeHtmlFetcher::class)->fetchHtml('https://president.gov.ua/documents/8752026-61465'))->toBe($html);

    Http::assertNothingSent();
});

it('caches the decree html that has no decree number in the url under a hashed name', function () {
    $url = 'https://www.president.gov.ua/documents/changes';
    $html = decreeFixture('875_2026.html');

    Http::fake([$url => Http::response($html, 200)]);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    Storage::disk('local')->assertExists('decrees/uncategorized/hash-'.hash('sha256', $url).'.html');
});

it('sends the headers that the decree site expects from a browser', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('875_2026.html'), 200)]);

    app(DecreeHtmlFetcher::class)->fetchHtml($url);

    Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent')
        && $request->hasHeader('Accept-Language')
        && $request->hasHeader('Accept-Encoding')
        && $request->hasHeader('Sec-Ch-Ua')
        && $request->hasHeader('Sec-Fetch-Mode'));
});

it('rejects the response status that marks a failure after the retries', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response('Service Unavailable', 503)]);

    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml($url))
        ->toThrow(DecreeParseException::class, 'Неочікуваний статус відповіді указу');

    Storage::disk('local')->assertMissing(decreeCachePath('875', '2026'));

    Http::assertSentCount(3);
});

it('asks again for the page the site protection interrupted', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Http::fake([
        $url => Http::sequence()
            ->push(decreeFixture('akamai_challenge.html'), 200)
            ->push($html, 200),
    ]);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    Http::assertSentCount(2);
    expect(Storage::disk('local')->get(decreeCachePath('875', '2026')))->toBe($html);
});

it('rejects the page the site protection keeps answering with instead of the decree', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response(decreeFixture('akamai_challenge.html'), 200)]);

    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml($url))
        ->toThrow(DecreeParseException::class, 'Замість сторінки указу надійшла відповідь захисту сайту');

    Storage::disk('local')->assertMissing(decreeCachePath('875', '2026'));

    Http::assertSentCount(3);
});

it('carries the cookies of the answer into the next request', function () {
    $firstUrl = decreeUrl('8752026-61465');
    $secondUrl = decreeUrl('8742026-61455');

    Http::fake([
        $firstUrl => Http::response(decreeFixture('875_2026.html'), 200, [
            'Set-Cookie' => 'bm_s=protection-session; Domain=.president.gov.ua; Path=/',
        ]),
        $secondUrl => Http::response(decreeFixture('875_2026.html'), 200),
    ]);

    $fetcher = app(DecreeHtmlFetcher::class);

    $fetcher->fetchHtml($firstUrl);
    $fetcher->fetchHtml($secondUrl);

    Http::assertSent(fn ($request): bool => $request->url() === $secondUrl
        && $request->hasHeader('Cookie', 'bm_s=protection-session'));
});

it('keeps the cookies of the protection session between the fetcher instances', function () {
    $firstUrl = decreeUrl('8752026-61465');
    $secondUrl = decreeUrl('8742026-61455');

    Http::fake([
        $firstUrl => Http::response(decreeFixture('875_2026.html'), 200, [
            'Set-Cookie' => 'bm_s=protection-session; Domain=.president.gov.ua; Path=/',
        ]),
        $secondUrl => Http::response(decreeFixture('875_2026.html'), 200),
    ]);

    app(DecreeHtmlFetcher::class)->fetchHtml($firstUrl);

    $this->app->forgetInstance(DecreeHtmlFetcher::class);

    app(DecreeHtmlFetcher::class)->fetchHtml($secondUrl);

    Http::assertSent(fn ($request): bool => $request->url() === $secondUrl
        && $request->hasHeader('Cookie', 'bm_s=protection-session'));
});

it('returns and caches the body of a status that is not a failure', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake([$url => Http::response('', 204)]);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe('');

    Storage::disk('local')->assertExists(decreeCachePath('875', '2026'));
});

it('reports a connection error when the decree site cannot be reached', function () {
    $url = decreeUrl('8752026-61465');

    Http::fake(fn (): never => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml($url))
        ->toThrow(DecreeParseException::class, 'Помилка з\'єднання під час отримання указу');

    Storage::disk('local')->assertMissing(decreeCachePath('875', '2026'));
});

it('returns the decree html even when the cache cannot be written', function () {
    $url = decreeUrl('8752026-61465');
    $html = decreeFixture('875_2026.html');

    Http::fake([$url => Http::response($html, 200)]);
    Log::spy();

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->once()->with(decreeCachePath('875', '2026'))->andReturnFalse();
    $disk->shouldReceive('exists')->once()->with('decrees/cookies.json')->andReturnFalse();
    $disk->shouldReceive('put')->once()->andThrow(new RuntimeException('Permission denied'));
    Storage::set('local', $disk);

    expect(app(DecreeHtmlFetcher::class)->fetchHtml($url))->toBe($html);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Unable to cache decree HTML on disk.', Mockery::type('array'));
});

it('rejects a decree url that is not using https', function () {
    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml('http://www.president.gov.ua/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Дозволено лише схему HTTPS');
});

it('rejects a decree url of a foreign host', function () {
    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml('https://example.com/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Неприпустимий хост URL указу');
});

it('rejects a decree url whose host only ends with the allowed host', function () {
    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml('https://president.gov.ua.example.com/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Неприпустимий хост URL указу');
});

it('rejects a decree url that has no host at all', function () {
    expect(fn () => app(DecreeHtmlFetcher::class)->fetchHtml('/documents/8752026-61465'))
        ->toThrow(InvalidArgumentException::class, 'Дозволено лише схему HTTPS');
});
