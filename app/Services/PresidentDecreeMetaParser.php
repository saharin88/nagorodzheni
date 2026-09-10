<?php

namespace App\Services;

use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use Carbon\CarbonImmutable;
use Dom\HTMLDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Uri\Rfc3986\Uri;

class PresidentDecreeMetaParser implements DecreeMetaParser
{
    private const string ALLOWED_HOST = 'president.gov.ua';

    private const array UKRAINIAN_MONTHS = [
        'січня' => 1, 'лютого' => 2, 'березня' => 3, 'квітня' => 4,
        'травня' => 5, 'червня' => 6, 'липня' => 7, 'серпня' => 8,
        'вересня' => 9, 'жовтня' => 10, 'листопада' => 11, 'грудня' => 12,
    ];

    /** @var array<string, array{number: string, date: string}> */
    private array $runtimeCache = [];

    public function getDecreeNumber(string $decreeUrl): string
    {
        return $this->getParsedMeta($decreeUrl)['number'];
    }

    public function getDecreeDate(string $decreeUrl): CarbonImmutable
    {
        return CarbonImmutable::parse($this->getParsedMeta($decreeUrl)['date']);
    }

    /**
     * @return array{number: string, date: string}
     */
    private function getParsedMeta(string $decreeUrl): array
    {
        $normalizedUrl = $this->normalizeAndValidateUrl($decreeUrl);

        return $this->runtimeCache[$normalizedUrl] ??= $this->parseMeta($this->getCachedHtml($normalizedUrl), $normalizedUrl);
    }

    private function normalizeAndValidateUrl(string $url): string
    {
        $uri = Uri::parse($url);

        if ($uri->getScheme() !== 'https') {
            throw new InvalidArgumentException("Only HTTPS scheme is allowed: {$url}");
        }

        $host = $uri->getHost();
        if ($host === null || ! str_ends_with(mb_strtolower($host), self::ALLOWED_HOST)) {
            throw new InvalidArgumentException("Invalid decree URL host: {$url}");
        }

        return $uri->toString();
    }

    private function getCachedHtml(string $decreeUrl): string
    {
        $htmlCachePath = $this->htmlCachePath($decreeUrl);

        if (File::exists($htmlCachePath)) {
            return File::get($htmlCachePath);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Language' => 'uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control' => 'max-age=0',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',

                'Sec-Ch-Ua' => '"Not)A;Brand";v="99", "Google Chrome";v="127", "Chromium";v="127"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
            ])
                ->withOptions([
                    'version' => 2.0,
                ])
                ->connectTimeout(15)
                ->timeout(15)
                ->retry([250, 500, 1000])
                ->get($decreeUrl);

        } catch (ConnectionException $e) {
            throw new DecreeParseException(
                __('Connection error while fetching the decree [:url]: :message', [
                    'url' => $decreeUrl,
                    'message' => $e->getMessage(),
                ])
            );
        }

        if ($response->status() !== 200) {
            throw new DecreeParseException(
                __('Unexpected decree response status [:url]: :status', [
                    'url' => $decreeUrl,
                    'status' => $response->status(),
                ])
            );
        }

        $html = $response->body();

        $this->parseMeta($html, $decreeUrl);

        $this->storeHtmlCache($htmlCachePath, $html, $decreeUrl);

        return $html;
    }

    private function storeHtmlCache(string $htmlCachePath, string $html, string $decreeUrl): void
    {
        try {
            File::ensureDirectoryExists(dirname($htmlCachePath));
            File::put($htmlCachePath, $html);
        } catch (Throwable $exception) {
            Log::warning('Unable to cache decree HTML on disk.', [
                'url' => $decreeUrl,
                'path' => $htmlCachePath,
                'exception' => $exception,
            ]);
        }
    }

    private function htmlCachePath(string $decreeUrl): string
    {
        $cacheToken = hash('sha256', $decreeUrl);

        return storage_path("framework/cache/decrees/$cacheToken.html");
    }

    /**
     * @return array{number: string, date: string}
     */
    private function parseMeta(string $html, string $decreeUrl): array
    {
        $document = HTMLDocument::createFromString($html);
        $this->assertAwardDecree($document, $decreeUrl);

        return [
            'number' => $this->parseDecreeNumber($document, $decreeUrl),
            'date' => $this->parseDecreeDate($document, $decreeUrl)->toDateString(),
        ];
    }

    private function assertAwardDecree(HTMLDocument $document, string $decreeUrl): void
    {
        $description = trim((string) $document->querySelector('meta[name="description"]')?->getAttribute('content'));
        $ogDescription = trim((string) $document->querySelector('meta[property="og:description"]')?->getAttribute('content'));
        $twitterDescription = trim((string) $document->querySelector('meta[name="twitter:description"]')?->getAttribute('content'));
        $shortDesc = trim((string) $document->querySelector('.short_desc p')?->textContent);

        $haystack = [
            $description,
            $ogDescription,
            $twitterDescription,
            $shortDesc,
        ]
                |> array_filter(...)
                |> (fn ($x) => implode(' ', $x))
                |> mb_strtolower(...);

        Log::info($haystack);

        if (! str_contains($haystack, 'про відзначення державними нагородами')) {
            throw new DecreeParseException(
                "Decree is not about state awards [{$decreeUrl}]."
            );
        }
    }

    private function parseDecreeNumber(HTMLDocument $document, string $decreeUrl): string
    {
        $heading = trim((string) $document->querySelector('.document_page h1[itemprop="name"]')?->textContent);

        if ($heading === '') {
            $heading = trim((string) $document->querySelector('title')?->textContent);
        }

        $normalizedHeading = preg_replace('/\s+/u', ' ', $heading);

        if (! preg_match('/№\s*([0-9]+\/[0-9]{4})/u', $normalizedHeading, $matches)) {
            throw new DecreeParseException("Unable to parse decree number [{$decreeUrl}].");
        }

        return $matches[1];
    }

    private function parseDecreeDate(HTMLDocument $document, string $decreeUrl): CarbonImmutable
    {
        $articleBodyText = trim((string) $document->querySelector('div[itemprop="articleBody"]')?->textContent);
        $normalizedText = preg_replace('/\s+/u', ' ', $articleBodyText);

        preg_match_all('/\b(\d{1,2}\s+[а-яіїєґ]+\s+\d{4}\s+року)\b/ui', $normalizedText, $matches);

        if (empty($matches[1])) {
            throw new DecreeParseException("Unable to parse decree date [{$decreeUrl}].");
        }

        $dateLiteral = end($matches[1]);

        return $this->resolveUkrainianDate($dateLiteral, $decreeUrl);
    }

    private function resolveUkrainianDate(string $dateLiteral, string $decreeUrl): CarbonImmutable
    {
        if (! preg_match('/^(?<day>\d{1,2})\s+(?<month>[а-яіїєґ]+)\s+(?<year>\d{4})\s+року$/ui', trim($dateLiteral),
            $parts)) {
            throw new DecreeParseException("Unexpected decree date format [{$decreeUrl}]: {$dateLiteral}");
        }

        $month = self::UKRAINIAN_MONTHS[mb_strtolower($parts['month'])] ?? null;

        if ($month === null) {
            throw new DecreeParseException("Unknown Ukrainian month [{$decreeUrl}]: {$parts['month']}");
        }

        return CarbonImmutable::createFromFormat('!Y-n-j', "{$parts['year']}-{$month}-{$parts['day']}")
            ?: throw new DecreeParseException("Invalid decree date value [{$decreeUrl}]: {$dateLiteral}");
    }
}
