<?php

namespace App\Services;

use App\Contracts\DecreeHtmlFetcher;
use App\Exceptions\DecreeParseException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Uri\Rfc3986\Uri;

class PresidentDecreeHtmlFetcher implements DecreeHtmlFetcher
{
    private const string ALLOWED_HOST = 'president.gov.ua';

    /** The file that keeps the cookies the site protection hands out. */
    private const string COOKIE_FILE_PATH = 'decrees/cookies.json';

    /** How many times the page the site protection interrupted is requested again. */
    private const int MAX_ATTEMPTS = 3;

    /** The seconds to wait before every attempt that follows an interrupted one. */
    private const array RETRY_DELAYS = [1, 3];

    /**
     * The markup of the pages the Akamai protection serves instead of the requested one.
     *
     * The interstitial challenge carries the container of the script that solves it,
     * while the access denied page links to the Akamai error host.
     */
    private const array PROTECTION_PAGE_MARKERS = [
        'sec-if-cpt-container',
        'errors.edgesuite.net',
    ];

    /**
     * The headers a Chrome browser sends when it opens a document.
     *
     * Both the order and the completeness matter: the site protection answers with the
     * access denied page as soon as the client hints or the fetch metadata are missing.
     */
    private const array BROWSER_HEADERS = [
        'Sec-Ch-Ua' => '"Not)A;Brand";v="99", "Google Chrome";v="127", "Chromium";v="127"',
        'Sec-Ch-Ua-Mobile' => '?0',
        'Sec-Ch-Ua-Platform' => '"Windows"',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-User' => '?1',
        'Sec-Fetch-Dest' => 'document',
        'Accept-Encoding' => 'gzip, deflate, br, zstd',
        'Accept-Language' => 'uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control' => 'max-age=0',
    ];

    private ?CookieJarInterface $cookieJar = null;

    /**
     * The Set-Cookie lines the site answered with, keyed by the cookie name.
     *
     * @var array<string, string>
     */
    private array $setCookieHeaders = [];

    public function fetchHtml(string $url): string
    {
        $normalizedUrl = $this->normalizeAndValidateUrl($url);
        $filePath = $this->getCacheFilePath($normalizedUrl);

        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->get($filePath);
        }

        $html = $this->downloadHtml($normalizedUrl);

        $this->storeHtmlCache($filePath, $html, $normalizedUrl);

        return $html;
    }

    public function fetchFreshHtml(string $url): string
    {
        return $this->downloadHtml($this->normalizeAndValidateUrl($url));
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

    /**
     * Get the page, asking again for the attempts the site protection interrupts.
     *
     * The protection answers with a page of its own, and it does so with a successful
     * status, so the answer is checked before it is handed over or written to the cache.
     *
     * @throws DecreeParseException when the protection keeps interrupting the request
     */
    private function downloadHtml(string $url): string
    {
        $attempt = 0;

        while (true) {
            if ($attempt > 0) {
                Sleep::sleep(self::RETRY_DELAYS[$attempt - 1]);
            }

            $attempt++;

            try {
                $html = $this->requestHtml($url);

                if (! $this->isProtectionPage($html)) {
                    return $html;
                }

                $failure = new DecreeParseException(
                    __('The site protection answered instead of the decree page [:url].', ['url' => $url])
                );
            } catch (ConnectionException $exception) {
                $failure = new DecreeParseException(
                    __('Connection error while fetching the decree [:url]: :message', [
                        'url' => $url,
                        'message' => $exception->getMessage(),
                    ]),
                    previous: $exception,
                );
            } catch (DecreeParseException $exception) {
                $failure = $exception;
            }

            if ($attempt >= self::MAX_ATTEMPTS) {
                throw $failure;
            }

            Log::warning('Retrying the decree page the site protection interrupted.', [
                'url' => $url,
                'attempt' => $attempt,
                'exception' => $failure,
            ]);
        }
    }

    /**
     * Request the page with the session the site protection hands out.
     */
    private function requestHtml(string $url): string
    {
        $response = Http::withHeaders(self::BROWSER_HEADERS)
            ->withOptions([
                'version' => 2.0,
                'cookies' => $this->getCookieJar(),
            ])
            ->connectTimeout(15)
            ->timeout(15)
            ->get($url);

        $this->rememberCookies($response);

        if ($response->failed()) {
            throw new DecreeParseException(
                __('Unexpected decree response status [:url]: :status', [
                    'url' => $url,
                    'status' => $response->status(),
                ])
            );
        }

        return $response->body();
    }

    /**
     * Tell whether the site protection answered with a page of its own.
     */
    private function isProtectionPage(string $html): bool
    {
        foreach (self::PROTECTION_PAGE_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the jar that carries the cookies of the protection session between the requests.
     */
    private function getCookieJar(): CookieJarInterface
    {
        if ($this->cookieJar instanceof CookieJarInterface) {
            return $this->cookieJar;
        }

        $this->cookieJar = new CookieJar;

        foreach ($this->readStoredCookies() as $setCookieHeader) {
            $cookie = $this->parseCookie($setCookieHeader);

            if ($cookie instanceof SetCookie) {
                $this->cookieJar->setCookie($cookie);
            }
        }

        return $this->cookieJar;
    }

    /**
     * Keep the cookies of the answer, so that the next request carries the session.
     */
    private function rememberCookies(Response $response): void
    {
        $setCookieHeaders = $this->getSetCookieHeaders($response);

        if ($setCookieHeaders === []) {
            return;
        }

        foreach ($setCookieHeaders as $setCookieHeader) {
            $this->setCookieHeaders[Str::before($setCookieHeader, '=')] = $setCookieHeader;
        }

        $this->storeCookies();
    }

    /**
     * Get the Set-Cookie lines of the answer.
     *
     * The name of the header keeps the case the site sent it in, and that case differs
     * between the HTTP versions, so the lines are collected by a case insensitive lookup.
     *
     * @return list<string>
     */
    private function getSetCookieHeaders(Response $response): array
    {
        $setCookieHeaders = [];

        foreach ($response->headers() as $name => $values) {
            if (! is_string($name) || strcasecmp($name, 'Set-Cookie') !== 0 || ! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (is_string($value)) {
                    $setCookieHeaders[] = $value;
                }
            }
        }

        return $setCookieHeaders;
    }

    /**
     * @return list<string>
     */
    private function readStoredCookies(): array
    {
        $disk = Storage::disk('local');

        try {
            if (! $disk->exists(self::COOKIE_FILE_PATH)) {
                return [];
            }

            $cookies = json_decode((string) $disk->get(self::COOKIE_FILE_PATH), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            Log::warning('Unable to read the stored decree site cookies.', ['exception' => $exception]);

            return [];
        }

        if (! is_array($cookies)) {
            return [];
        }

        $stored = [];

        foreach ($cookies as $cookie) {
            if (is_string($cookie)) {
                $stored[] = $cookie;
            }
        }

        return $stored;
    }

    private function storeCookies(): void
    {
        $cookies = [];

        foreach ($this->setCookieHeaders as $setCookieHeader) {
            $cookie = $this->parseCookie($setCookieHeader);

            if ($cookie instanceof SetCookie && ! $cookie->isExpired()) {
                $cookies[] = $setCookieHeader;
            }
        }

        try {
            Storage::disk('local')->put(self::COOKIE_FILE_PATH, json_encode($cookies, JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            Log::warning('Unable to store the decree site cookies.', ['exception' => $exception]);
        }
    }

    private function parseCookie(string $setCookieHeader): ?SetCookie
    {
        try {
            return SetCookie::fromString($setCookieHeader);
        } catch (Throwable $exception) {
            Log::warning('Unable to parse a decree site cookie.', [
                'cookie' => Str::before($setCookieHeader, '='),
                'exception' => $exception,
            ]);

            return null;
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
