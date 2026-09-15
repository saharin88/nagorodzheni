<?php

namespace App\Services;

use App\Contracts\AwardDecreeListParser as AwardDecreeListParserContract;
use App\Contracts\DecreeHtmlFetcher;
use App\Exceptions\DecreeParseException;
use Dom\HTMLDocument;
use Illuminate\Support\Str;
use Throwable;

class PresidentAwardDecreeListParser implements AwardDecreeListParserContract
{
    private const string DECREE_NUMBER_PATTERN = '/№\s*(?<number>\d+\/\d{4})/u';

    public function __construct(
        private readonly DecreeHtmlFetcher $htmlFetcher,
    ) {}

    /**
     * @return list<array{number: string, url: string}>
     */
    public function getDecrees(string $listUrl): array
    {
        $results = $this->parseDocument($this->htmlFetcher->fetchFreshHtml($listUrl), $listUrl)
            ->querySelector('.search_result_body');

        if ($results === null) {
            throw new DecreeParseException(__('Unable to parse the decree list [:url].', ['url' => $listUrl]));
        }

        $decrees = [];

        foreach ($results->querySelectorAll('.doc_item') as $item) {
            $link = $item->querySelector('h3 a');

            $number = Str::match(self::DECREE_NUMBER_PATTERN, Str::squish((string) $link?->textContent));
            $url = trim((string) $link?->getAttribute('href'));

            if ($number === '' || $url === '') {
                continue;
            }

            $decrees[] = [
                'number' => $number,
                'url' => $url,
            ];
        }

        return $decrees;
    }

    private function parseDocument(string $html, string $listUrl): HTMLDocument
    {
        try {
            return HTMLDocument::createFromString($html);
        } catch (Throwable $exception) {
            throw new DecreeParseException(
                __('Unable to parse the decree list [:url].', ['url' => $listUrl]),
                previous: $exception,
            );
        }
    }
}
