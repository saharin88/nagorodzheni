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

    /**
     * Every awarding paragraph opens with the verb, whether it holds the award alone
     * or carries the single awardee of the decree in the same sentence.
     */
    private const string AWARD_PARAGRAPH_PATTERN = '/^(?:Нагородити|Присвоїти)\s+(?<award>.+)$/u';

    /**
     * An awarding paragraph that carries its awardee too is split on the awardee name,
     * which the fully capitalized surname followed by the given name marks. The given
     * name keeps the degree of an order, written as "ІІ ступеня", from passing for a
     * surname.
     */
    private const string INLINE_AWARDEE_PATTERN = '/^(?<award>.+?)\s+(?<awardee>[А-ЯІЇЄҐ][А-ЯІЇЄҐ’\'ʼ`´-]+\s+[А-ЯІЇЄҐ][\p{L}’\'ʼ`´-]*(?:\s.*)?)$/u';

    /**
     * The dash of a decree separates the awardee name from the rank, and every decree
     * writes it as an en or an em dash. It is tried first, because a hyphen appears
     * inside surnames and given names as well, as in "КОСТЕНКО-СИДОРЕНКА" and
     * "Магіра-огли".
     */
    private const string AWARDEE_DASH_PATTERN = '/^(?<full_name>.+?)(?:\s*\((?<is_posthumous>посмертно)\))?\s*[—–]\s*(?<rank>.+)$/u';

    /**
     * The decrees that typed their dash as a hyphen separate the name from the rank
     * with it, however it is spaced: "Юрійовичу- лейтенанту", "Сергійовича -молодшого"
     * and "Андрійовичу-молодшому сержанту". A hyphen that an uppercase letter follows
     * belongs to the name, as in "КОСТЕНКО-СИДОРЕНКА".
     */
    private const string AWARDEE_HYPHEN_PATTERN = '/^(?<full_name>.+?)(?:\s*\((?<is_posthumous>посмертно)\))?\s*-(?![А-ЯІЇЄҐ])\s*(?<rank>.+)$/u';

    /**
     * The decrees about the Hero of Ukraine title write the marker after the rank,
     * as in "– полковнику (посмертно)", while the decrees about state awards write
     * it between the name and the rank.
     */
    private const string TRAILING_POSTHUMOUS_PATTERN = '/(?:^|\s)\((?<is_posthumous>посмертно)\)\s*\.?\s*$/u';

    private const string AWARD_QUOTE_PATTERN = '/["“”„‟«»]/u';

    /**
     * The Hero of Ukraine title is awarded with the order "Золота Зірка" or with the
     * order of the State, and the decrees name every one of those combinations at
     * length. Some of them write the title in the genitive case. The title stays a
     * single award, so all of those names collapse into its name.
     */
    private const string HERO_OF_UKRAINE_AWARD = 'звання Герой України';

    private const string HERO_OF_UKRAINE_AWARD_PATTERN = '/^звання Геро[яй] України(?!\p{L})/u';

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

        $haystack = mb_strtoupper(implode(' ', array_filter([
            $description,
            $ogDescription,
            $twitterDescription,
            $shortDesc,
        ])));

        if (
            ! str_contains($haystack, 'ПРО ВІДЗНАЧЕННЯ ДЕРЖАВНИМИ НАГОРОДАМИ')
            && ! str_contains($haystack, 'ЗВАННЯ ГЕРОЙ УКРАЇНИ')
        ) {
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

            if ($text === '') {
                continue;
            }

            $awardParagraph = $this->parseAwardParagraph($text);
            $awardeeText = $text;

            if ($awardParagraph !== null) {
                $award = $awardParagraph['award'];
                $awardeeText = $awardParagraph['awardee'] ?? '';

                if ($awardeeText === '') {
                    continue;
                }
            }

            $awardee = $this->parseAwardee($awardeeText, $award);

            if ($awardee !== null) {
                $awardees[] = $awardee;
            }
        }

        return $awardees;
    }

    /**
     * Read the paragraph that opens with the awarding verb.
     *
     * The award usually takes the whole paragraph and leaves the awardees to the
     * paragraphs below it, but the decrees about the Hero of Ukraine title often
     * carry the award and its single awardee in one paragraph.
     *
     * @return array{award: string, awardee: string|null}|null
     */
    private function parseAwardParagraph(string $text): ?array
    {
        if (preg_match(self::AWARD_PARAGRAPH_PATTERN, $text, $matches) !== 1) {
            return null;
        }

        $inline = $this->splitInlineAwardee($matches['award']);

        if ($inline === null) {
            return [
                'award' => $this->normalizeAwardName($text),
                'awardee' => null,
            ];
        }

        return [
            'award' => $this->normalizeAwardName($inline['award']),
            'awardee' => $inline['awardee'],
        ];
    }

    /**
     * Split the sentence that carries both the award and its single awardee.
     *
     * @return array{award: string, awardee: string}|null
     */
    private function splitInlineAwardee(string $awardText): ?array
    {
        if (preg_match(self::INLINE_AWARDEE_PATTERN, $awardText, $matches) !== 1) {
            return null;
        }

        if ($this->isSplitInsideQuotes($matches['award'])) {
            return null;
        }

        return [
            'award' => $matches['award'],
            'awardee' => $matches['awardee'],
        ];
    }

    /**
     * Tell whether the split left the award in the middle of a quoted name.
     *
     * The decrees that award honorary titles keep the title in quotes, as in
     * `Присвоїти почесне звання «ЗАСЛУЖЕНИЙ ЛІКАР УКРАЇНИ»`, so an award that ends
     * on an opening quote marks that title rather than an awardee.
     *
     * A decree that simply wrote the wrong closing quote, as in
     * `ордена “Золота Зірка“ ДМИТРУКУ Олегу Володимировичу`, keeps an even number
     * of the quotes behind the split, so it still passes.
     */
    private function isSplitInsideQuotes(string $award): bool
    {
        $lastCharacter = mb_substr($award, -1);

        if (! in_array($lastCharacter, ['«', '“', '„', '"'], true)) {
            return false;
        }

        return mb_substr_count($award, $lastCharacter) % 2 === 1;
    }

    /**
     * @return array{full_name: string, rank: string, award: string, is_posthumous: bool}|null
     */
    private function parseAwardee(string $text, string $award): ?array
    {
        $withoutTrailingMarker = (string) preg_replace(self::TRAILING_POSTHUMOUS_PATTERN, '', $text);
        $isPosthumous = $withoutTrailingMarker !== $text;
        $text = Str::squish($withoutTrailingMarker);

        $split = $this->splitAwardee($text);

        if ($split === null) {
            return null;
        }

        return [
            'full_name' => Str::convertCase(Str::squish($split['full_name']), MB_CASE_TITLE),
            'rank' => Str::squish(rtrim($split['rank'], ' .')),
            'award' => $award,
            'is_posthumous' => $isPosthumous || $split['is_posthumous'] !== '',
        ];
    }

    /**
     * Split the line that carries the awardee into the name and the rank.
     *
     * @return array{full_name: string, rank: string, is_posthumous: string}|null
     */
    private function splitAwardee(string $text): ?array
    {
        foreach ([self::AWARDEE_DASH_PATTERN, self::AWARDEE_HYPHEN_PATTERN] as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return [
                    'full_name' => $matches['full_name'],
                    'rank' => $matches['rank'],
                    'is_posthumous' => $matches['is_posthumous'],
                ];
            }
        }

        return null;
    }

    private function normalizeAwardName(string $awardText): string
    {
        $awardName = Str::squish((string) preg_replace('/^(Нагородити|Присвоїти)\s+/u', '', $awardText));

        if (preg_match(self::HERO_OF_UKRAINE_AWARD_PATTERN, $awardName) === 1) {
            return self::HERO_OF_UKRAINE_AWARD;
        }

        return $this->normalizeAwardQuotes($awardName);
    }

    private function normalizeAwardQuotes(string $awardName): string
    {
        $quotePosition = 0;

        return (string) preg_replace_callback(
            self::AWARD_QUOTE_PATTERN,
            function () use (&$quotePosition): string {
                return $quotePosition++ % 2 === 0 ? '«' : '»';
            },
            $awardName
        );
    }
}
