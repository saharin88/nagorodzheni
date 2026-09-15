<?php

namespace App\Services;

use App\Contracts\DecreeAwardeeParser;
use App\Contracts\DecreeHtmlFetcher;
use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use Carbon\CarbonImmutable;
use Dom\HTMLDocument;
use Illuminate\Support\Str;

class PresidentDecreeParser implements DecreeAwardeeParser, DecreeMetaParser
{
    private const array UKRAINIAN_MONTHS = [
        'січня' => 1, 'лютого' => 2, 'березня' => 3, 'квітня' => 4,
        'травня' => 5, 'червня' => 6, 'липня' => 7, 'серпня' => 8,
        'вересня' => 9, 'жовтня' => 10, 'листопада' => 11, 'грудня' => 12,
    ];

    private const string AWARDEE_PATTERN = '/^(?<full_name>.+?)(?:\s*\((?<is_posthumous>посмертно)\))?(?:\s*[—–]\s*|\s+-\s+)(?<rank>.+)$/u';

    /** @var array<string, array{number: string, date: string}> */
    private array $runtimeCache = [];

    /** @var array<string, list<array{full_name: string, rank: string, award: string, is_posthumous: bool}>> */
    private array $awardeesCache = [];

    public function __construct(
        private readonly DecreeHtmlFetcher $htmlFetcher
    ) {}

    public function getDecreeNumber(string $decreeUrl): string
    {
        return $this->getParsedMeta($decreeUrl)['number'];
    }

    public function getDecreeDate(string $decreeUrl): CarbonImmutable
    {
        return CarbonImmutable::parse($this->getParsedMeta($decreeUrl)['date']);
    }

    /**
     * @return list<array{full_name: string, rank: string, award: string, is_posthumous: bool}>
     */
    public function getAwardees(string $decreeUrl): array
    {
        return $this->awardeesCache[$decreeUrl] ??= $this->parseAwardees(
            $this->htmlFetcher->fetchHtml($decreeUrl),
            $decreeUrl,
        );
    }

    /**
     * @return array{number: string, date: string}
     */
    private function getParsedMeta(string $decreeUrl): array
    {
        return $this->runtimeCache[$decreeUrl] ??= $this->parseMeta(
            $this->htmlFetcher->fetchHtml($decreeUrl),
            $decreeUrl
        );
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

        $haystack = mb_strtolower(implode(' ', array_filter([
            $description,
            $ogDescription,
            $twitterDescription,
            $shortDesc,
        ])));

        if (! str_contains($haystack, 'про відзначення державними нагородами')) {
            throw new DecreeParseException(
                __('Decree is not about state awards [:url].', ['url' => $decreeUrl])
            );
        }
    }

    private function parseDecreeNumber(HTMLDocument $document, string $decreeUrl): string
    {
        $heading = trim((string) $document->querySelector('.document_page h1[itemprop="name"]')?->textContent)
            ?: trim((string) $document->querySelector('title')?->textContent);

        $normalizedHeading = Str::squish($heading);

        $number = Str::match('/№\s*([0-9]+\/[0-9]{4})/u', $normalizedHeading);

        if (empty($number)) {
            throw new DecreeParseException(__('Unable to parse decree number [:url].', ['url' => $decreeUrl]));
        }

        return $number;
    }

    private function parseDecreeDate(HTMLDocument $document, string $decreeUrl): CarbonImmutable
    {
        $articleBodyText = trim((string) $document->querySelector('div[itemprop="articleBody"]')?->textContent);
        $dates = Str::matchAll('/\b(\d{1,2}\s+[а-яіїєґ]+\s+\d{4}\s+року)\b/ui', Str::squish($articleBodyText));

        if ($dates->isEmpty()) {
            throw new DecreeParseException(__('Unable to parse decree date [:url].', ['url' => $decreeUrl]));
        }

        $dateLiteral = $dates->last();

        return $this->resolveUkrainianDate($dateLiteral, $decreeUrl);
    }

    private function resolveUkrainianDate(string $dateLiteral, string $decreeUrl): CarbonImmutable
    {
        if (! preg_match('/^(?<day>\d{1,2})\s+(?<month>[а-яіїєґ]+)\s+(?<year>\d{4})\s+року$/ui', trim($dateLiteral), $parts)) {
            throw new DecreeParseException(__('Unexpected decree date format [:url]: :date', [
                'url' => $decreeUrl,
                'date' => $dateLiteral,
            ]));
        }

        $month = self::UKRAINIAN_MONTHS[mb_strtolower($parts['month'])] ?? null;

        if ($month === null) {
            throw new DecreeParseException(__('Unknown Ukrainian month [:url]: :month', [
                'url' => $decreeUrl,
                'month' => $parts['month'],
            ]));
        }

        return CarbonImmutable::createFromFormat('!Y-n-j', "{$parts['year']}-{$month}-{$parts['day']}")
            ?: throw new DecreeParseException(__('Invalid decree date value [:url]: :date', [
                'url' => $decreeUrl,
                'date' => $dateLiteral,
            ]));
    }

    /**
     * @return list<array{full_name: string, rank: string, award: string, is_posthumous: bool}>
     */
    private function parseAwardees(string $html, string $decreeUrl): array
    {
        $document = HTMLDocument::createFromString($html);
        $this->assertAwardDecree($document, $decreeUrl);

        $articleBody = $document->querySelector('div[itemprop="articleBody"]');

        if ($articleBody === null) {
            throw new DecreeParseException(__('Unable to parse decree awardees [:url].', ['url' => $decreeUrl]));
        }

        $awardees = [];
        $award = '';

        foreach ($articleBody->querySelectorAll('p') as $paragraph) {
            $text = Str::squish($paragraph->textContent);
            $heading = $paragraph->querySelector('strong');

            if ($heading !== null && Str::squish($heading->textContent) === $text) {
                $award = $this->normalizeAwardName($text);

                continue;
            }

            $awardee = $this->parseAwardee($text, $award);

            if ($awardee !== null) {
                $awardees[] = $awardee;
            }
        }

        return $awardees;
    }

    /**
     * @return array{full_name: string, rank: string, award: string, is_posthumous: bool}|null
     */
    private function parseAwardee(string $text, string $award): ?array
    {
        if (preg_match(self::AWARDEE_PATTERN, $text, $matches) !== 1) {
            return null;
        }

        return [
            'full_name' => Str::convertCase(Str::squish($matches['full_name']), MB_CASE_TITLE),
            'rank' => Str::squish(rtrim($matches['rank'], ' .')),
            'award' => $award,
            'is_posthumous' => $matches['is_posthumous'] !== '',
        ];
    }

    private function normalizeAwardName(string $heading): string
    {
        return Str::squish((string) preg_replace('/^(Нагородити|Присвоїти)\s+/u', '', $heading));
    }
}
