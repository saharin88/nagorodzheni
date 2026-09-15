<?php

use App\Contracts\AwardDecreeListParser;
use App\Contracts\DecreeHtmlFetcher;
use App\Exceptions\DecreeParseException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Serve the given list page through the fetcher contract, so that the parser tests
 * never touch the network or the disk cache of CachedPresidentDecreeFetcher.
 */
function fakeDecreeListHtml(string $html): void
{
    $fetcher = Mockery::mock(DecreeHtmlFetcher::class);
    $fetcher->shouldReceive('fetchFreshHtml')->andReturn($html);

    app()->instance(DecreeHtmlFetcher::class, $fetcher);
}

it('parses the decrees of the search page, newest first', function () {
    $listUrl = 'https://www.president.gov.ua/documents/decrees';

    fakeDecreeListHtml(decreeFixture('award_decrees_list.html'));

    expect(app(AwardDecreeListParser::class)->getDecrees($listUrl))->toBe([
        [
            'number' => '904/2026',
            'url' => 'https://www.president.gov.ua/documents/9042026-61557',
        ],
        [
            'number' => '902/2026',
            'url' => 'https://www.president.gov.ua/documents/9022026-61581',
        ],
        [
            'number' => '897/2026',
            'url' => 'https://www.president.gov.ua/documents/8972026-61565',
        ],
        [
            'number' => '875/2026',
            'url' => 'https://www.president.gov.ua/documents/8752026-61465',
        ],
    ]);
});

it('reads the list page from the site instead of the disk cache', function () {
    $listUrl = 'https://www.president.gov.ua/documents/decrees';

    $fetcher = Mockery::mock(DecreeHtmlFetcher::class);
    $fetcher->shouldReceive('fetchFreshHtml')->once()->with($listUrl)->andReturn(decreeFixture('award_decrees_list.html'));
    $fetcher->shouldNotReceive('fetchHtml');

    app()->instance(DecreeHtmlFetcher::class, $fetcher);

    app(AwardDecreeListParser::class)->getDecrees($listUrl);
});

it('skips the list entries that carry no decree number', function () {
    fakeDecreeListHtml(<<<'HTML'
        <!DOCTYPE html>
        <html lang="ua">
            <body>
                <div class="search_result_body">
                    <div class="doc_item">
                        <h3><a href="https://www.president.gov.ua/documents/9042026-61557">УКАЗ ПРЕЗИДЕНТА УКРАЇНИ №904/2026</a></h3>
                    </div>
                    <div class="doc_item">
                        <h3><a href="https://www.president.gov.ua/documents/9322026-61673">УКАЗ ПРЕЗИДЕНТА УКРАЇНИ</a></h3>
                    </div>
                    <div class="doc_item">
                        <h3>Розпорядження Президента України</h3>
                    </div>
                </div>
            </body>
        </html>
        HTML);

    expect(app(AwardDecreeListParser::class)->getDecrees('https://www.president.gov.ua/documents/decrees'))->toBe([
        [
            'number' => '904/2026',
            'url' => 'https://www.president.gov.ua/documents/9042026-61557',
        ],
    ]);
});

it('rejects the page that carries no list of the results', function () {
    $listUrl = 'https://www.president.gov.ua/documents/decrees';

    fakeDecreeListHtml('<html lang="ua"><body><p>Access is denied</p></body></html>');

    expect(fn () => app(AwardDecreeListParser::class)->getDecrees($listUrl))
        ->toThrow(DecreeParseException::class, __('Unable to parse the decree list [:url].', ['url' => $listUrl]));
});
