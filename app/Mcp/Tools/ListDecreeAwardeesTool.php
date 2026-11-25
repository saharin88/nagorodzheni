<?php

namespace App\Mcp\Tools;

use App\Contracts\AwardeeSearch;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_decree_awardees')]
#[Title('List the awardees of a decree')]
#[Description('List the people awarded by one presidential decree of Ukraine. Pass the decree number such as "1131/2026" or "1131" (extract the digits from wording like "указ № 1025"), or pass "latest" when the user says "в останньому указі", "у найновішому указі" or "в останньому указі, де є орден за мужність" (then the award and posthumous filters select the newest decree that contains them). The rows follow the order of the decree and contain rank, name as written in the decree, award with the posthumous marker, decree number and date. Without an award filter the response also carries the "awards" breakdown of the whole decree (every award with its count and the posthumous part of it), so a question such as "якими нагородами нагороджували в указі" is answered by ONE call: pass per_page 1 to keep the awardee rows short and read the breakdown instead of calling the tool once per award. Optionally narrow the list by award ("орден за мужність", "медаль за військову службу") and by posthumous status, so questions like "чи є в указі 1025 нагороджені орденом за мужність" are answered with the filtered count. Large lists are paginated: the response reports the total number of awardees, the matched number and the last page, so request the remaining pages with the "page" argument. If the year is omitted and the number exists in several years, the tool returns the candidate numbers instead of guessing. Ignore generic wording such as "державні нагороди" and do not pass it as the award.')]
#[IsReadOnly]
#[IsIdempotent]
class ListDecreeAwardeesTool extends AwardeeTool
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    private const MAX_PAGE = 200;

    private const KNOWN_AWARDS_IN_ERROR = 5;

    /**
     * The values of the decree number argument that ask for the newest decree.
     *
     * @var list<string>
     */
    private const LATEST_TOKENS = ['latest', 'last', 'newest', 'current', 'останній', 'останній указ', 'найновіший', 'найновітніший', 'поточний', 'актуальний'];

    public function __construct(
        private readonly AwardeeSearch $awardees,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'decree_number' => ['nullable', 'string', 'max:50'],
            'award' => ['nullable', 'string', 'max:200'],
            'is_posthumous' => ['nullable', 'boolean'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ], [
            'page.max' => 'At most '.self::MAX_PAGE.' pages can be requested in one call.',
            'per_page.max' => 'At most '.self::MAX_PER_PAGE.' records can be returned in one call.',
        ]);

        $number = preg_replace('/^[№#\s]+/u', '', trim((string) ($validated['decree_number'] ?? ''))) ?? '';
        $awardQuery = trim((string) ($validated['award'] ?? ''));
        $isPosthumous = ($validated['is_posthumous'] ?? null) !== null
            ? (bool) $validated['is_posthumous']
            : null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        if ($this->tokenCount($awardQuery) > self::MAX_AWARD_TOKENS) {
            return Response::error('Фраза нагороди надто широка. Передайте не більше '.self::MAX_AWARD_TOKENS.' слів у award.');
        }

        $awards = $awardQuery === '' ? collect() : $this->awardees->matchingAwards($awardQuery);

        if ($awardQuery !== '' && $awards->isEmpty()) {
            $knownAwards = $this->awardNames(
                $this->awardees->matchingAwards('')->take(self::KNOWN_AWARDS_IN_ERROR),
            );

            return Response::error($this->unknownAwardMessage($awardQuery, $knownAwards));
        }

        $awardIds = array_values($awards->pluck('id')->map(fn (int|string $id): int => (int) $id)->all());
        $matchedAwards = $this->awardNames($awards);
        $filters = $this->filtersPayload($awardQuery, $matchedAwards, $isPosthumous);

        $isLatest = $number === '' || in_array(mb_strtolower($number, 'UTF-8'), self::LATEST_TOKENS, true);

        if ($isLatest) {
            $decree = $this->awardees->findLatestDecree($awardIds, $isPosthumous);

            if (! $decree instanceof Decree) {
                $message = $awardQuery === '' && $isPosthumous === null
                    ? 'У реєстрі немає жодного указу.'
                    : 'Не знайдено жодного указу з такою нагородою.';

                return Response::error($message.' Спробуйте list_awards, щоб перевірити формулювання нагороди.');
            }
        } else {
            $decrees = $this->awardees->findDecrees($number);

            if ($decrees->isEmpty()) {
                return Response::error("Указ із номером «{$number}» не знайдено. Передайте номер у форматі «1131/2026» або «latest» для останнього указу.");
            }

            if ($decrees->count() > 1) {
                $candidates = $decrees->pluck('number')->implode(', ');

                return Response::error("Номер «{$number}» неоднозначний: знайдено укази {$candidates}. Уточніть рік, наприклад «{$decrees->first()->number}».");
            }

            $decree = $decrees->first();
        }

        $isFiltered = $matchedAwards !== [] || $isPosthumous !== null;

        $paginator = $this->awardees->paginateByDecree(
            $decree,
            $awardIds,
            $isPosthumous,
            $perPage,
            $page,
        );

        $total = $paginator->total();
        $decreeTotal = $isFiltered
            ? (int) ($decree->awardees_count ?? $total)
            : $total;
        $lastPage = $paginator->lastPage();

        if ($total > 0 && $page > $lastPage) {
            return Response::error("За цим запитом в указі № {$decree->number} лише {$total} записів, тобто {$lastPage} сторінок. Запитайте сторінку від 1 до {$lastPage}.");
        }

        $rows = array_map(
            fn (Awardee $awardee): array => $this->awardeeRow($awardee),
            array_values($paginator->items()),
        );

        $returned = count($rows);
        $awardBreakdown = $isFiltered ? [] : $this->awardBreakdown($decree);

        $structured = [
            'decree' => [
                'number' => $decree->number,
                'date' => $decree->date->toDateString(),
                'url' => $decree->url,
                'is_hero' => $decree->is_hero,
                'total_awardees' => $decreeTotal,
                'resolved_as_latest' => $isLatest,
            ],
            'filters' => $filters,
            'awards' => $awardBreakdown,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'total' => $total,
            'returned' => $returned,
            'awardees' => $rows,
        ];

        $date = $decree->date->format('d.m.Y');

        $lines = [
            $isLatest
                ? "Останній указ у реєстрі — № {$decree->number} від {$date}: нагороджених — {$decreeTotal}."
                : "Указ № {$decree->number} від {$date}: нагороджених — {$decreeTotal}.",
        ];

        if ($isLatest && $isFiltered) {
            $lines[] = 'Це найновіший указ, що відповідає фільтру.';
        }

        if ($isFiltered) {
            $criteria = [];

            if ($matchedAwards !== []) {
                $criteria[] = 'нагорода: '.implode('; ', $matchedAwards);
            }

            if ($isPosthumous !== null) {
                $criteria[] = $isPosthumous ? 'посмертне нагородження' : 'нагородження за життя';
            }

            $lines[] = 'За фільтром ('.implode('; ', $criteria)."): {$total}.";
        }

        $text = implode("\n", $lines);

        if ($awardBreakdown !== [] && $page === 1) {
            $text .= "\n\n".__('Awardees by award').':';
            $text .= "\n\n".$this->awardsTable($awardBreakdown);
        }

        $metaLines = [];

        if ($returned > 0) {
            $first = ($page - 1) * $perPage + 1;
            $last = $first + $returned - 1;

            $metaLines[] = "Показано записи {$first}–{$last} (сторінка {$page} із {$lastPage}).";
        }

        if ($decree->is_hero) {
            $metaLines[] = 'Указ про присвоєння звання Героя України.';
        }

        $metaLines[] = "Джерело: {$decree->url}";

        $text .= "\n\n".implode("\n", $metaLines);

        if ($returned > 0) {
            $text .= "\n\n".$this->awardeesTable($rows);
        }

        return Response::make(Response::text($text))
            ->withStructuredContent($structured);
    }

    /**
     * Map the award breakdown of a decree to the shared awards table rows.
     *
     * @return list<array{name: string, awardees_count: int, posthumous_awardees_count: int}>
     */
    private function awardBreakdown(Decree $decree): array
    {
        return array_values(
            $this->awardees->awardBreakdownByDecree($decree)
                ->map(fn (Award $award): array => $this->awardRow($award))
                ->all(),
        );
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'decree_number' => $schema->string()
                ->description('Decree number, for example "1131/2026" (the year may be omitted when the number is unique). Pass "latest" to use the newest decree in the registry, or the newest one that contains the requested award or posthumous records.')
                ->nullable(),
            'award' => $schema->string()
                ->description('Award phrase in Ukrainian, as the user said it: "орден за мужність", "медаль за військову службу", "орден Богдана Хмельницького ІІІ ступеня". Quotes, case and the degree wording do not matter. Use up to '.self::MAX_AWARD_TOKENS.' words. Omit it to list every award in the decree.')
                ->nullable(),
            'is_posthumous' => $schema->boolean()
                ->description('Set true to return only posthumous awards, false to return only awards conferred during life. Omit it to return both.')
                ->nullable(),
            'page' => $schema->integer()
                ->description('Page of the awardee list to return (1-'.self::MAX_PAGE.'), starting from 1.')
                ->min(1)
                ->max(self::MAX_PAGE)
                ->default(1),
            'per_page' => $schema->integer()
                ->description('Number of awardees per page (1-'.self::MAX_PER_PAGE.'). Defaults to '.self::DEFAULT_PER_PAGE.'.')
                ->min(1)
                ->max(self::MAX_PER_PAGE)
                ->default(self::DEFAULT_PER_PAGE),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'decree' => $schema->object([
                'number' => $schema->string()->required(),
                'date' => $schema->string()
                    ->description('Date of the decree in the YYYY-MM-DD format.')
                    ->required(),
                'url' => $schema->string()
                    ->description('URL of the decree on the President of Ukraine website.')
                    ->required(),
                'is_hero' => $schema->boolean()
                    ->description('Whether the decree confers the Hero of Ukraine title.')
                    ->required(),
                'total_awardees' => $schema->integer()
                    ->description('Total number of awardees in the decree, before any filter.')
                    ->required(),
                'resolved_as_latest' => $schema->boolean()
                    ->description('Whether the decree was resolved as the newest one instead of being given by number.')
                    ->required(),

            ])->required(),
            ...$this->filtersOutputSchema($schema),
            ...$this->awardsBreakdownOutputSchema($schema),
            'page' => $schema->integer()->required(),
            'per_page' => $schema->integer()->required(),
            'last_page' => $schema->integer()->required(),
            'total' => $schema->integer()
                ->description('Number of awardees that match the request, after the filters.')
                ->required(),
            'returned' => $schema->integer()
                ->description('Number of award records in this response.')
                ->required(),
            ...$this->awardeesOutputSchema($schema),
        ];
    }
}
