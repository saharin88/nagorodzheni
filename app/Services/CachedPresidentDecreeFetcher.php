<?php

namespace App\Services;

use App\Contracts\DecreeHtmlFetcher;
use App\Exceptions\DecreeParseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;
use Uri\Rfc3986\Uri;

class CachedPresidentDecreeFetcher implements DecreeHtmlFetcher
{
    private const string ALLOWED_HOST = 'president.gov.ua';

    public function fetchHtml(string $decreeUrl): string
    {
        $normalizedUrl = $this->normalizeAndValidateUrl($decreeUrl);
        $filePath = $this->getCacheFilePath($normalizedUrl);

        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->get($filePath);
        }

        $html = $this->downloadHtml($normalizedUrl);

        $this->storeHtmlCache($filePath, $html, $normalizedUrl);

        return $html;
    }

    private function normalizeAndValidateUrl(string $url): string
    {
        $uri = Uri::parse($url);

        if ($uri->getScheme() !== 'https') {
            throw new InvalidArgumentException(__('Only HTTPS scheme is allowed: :url', ['url' => $url]));
        }

        $host = $uri->getHost();
        if ($host === null || ! str_ends_with(mb_strtolower($host), self::ALLOWED_HOST)) {
            throw new InvalidArgumentException(__('Invalid decree URL host: :url', ['url' => $url]));
        }

        return $uri->toString();
    }

    private function downloadHtml(string $url): string
    {
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
                ->withOptions(['version' => 2.0])
                ->connectTimeout(15)
                ->timeout(15)
                ->retry([250, 500, 1000])
                ->get($url);

            if ($response->failed()) {
                throw new DecreeParseException(
                    __('Unexpected decree response status [:url]: :status', [
                        'url' => $url,
                        'status' => $response->status(),
                    ])
                );
            }

            return $response->body();

        } catch (ConnectionException $e) {
            throw new DecreeParseException(
                __('Connection error while fetching the decree [:url]: :message', [
                    'url' => $url,
                    'message' => $e->getMessage(),
                ])
            );
        }
    }

    private function getCacheFilePath(string $url): string
    {
        try {
            $info = $this->extractDecreeInfoFromUrl($url);

            return "decrees/{$info['year']}/{$info['number']}-{$info['year']}.html";

        } catch (InvalidArgumentException $e) {
            $cacheToken = hash('sha256', $url);

            return "decrees/uncategorized/hash-{$cacheToken}.html";
        }
    }

    /**
     * Витягує номер та рік указу з URL.
     *
     * @return array{number: string, year: string}
     */
    private function extractDecreeInfoFromUrl(string $url): array
    {
        if (preg_match('/\/documents\/(?<number>\d+)(?<year>\d{4})(?:-|$)/u', $url, $matches)) {
            return [
                'number' => $matches['number'],
                'year' => $matches['year'],
            ];
        }

        throw new InvalidArgumentException(__('Unable to parse decree info from URL: :url', ['url' => $url]));
    }

    private function storeHtmlCache(string $filePath, string $html, string $url): void
    {
        try {
            Storage::disk('local')->put($filePath, $html);
        } catch (Throwable $exception) {
            Log::warning('Unable to cache decree HTML on disk.', [
                'url' => $url,
                'path' => $filePath,
                'exception' => $exception,
            ]);
        }
    }
}
