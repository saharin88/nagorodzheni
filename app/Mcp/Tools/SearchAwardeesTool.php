<?php

namespace App\Mcp\Tools;

use App\Contracts\AwardeeSearch;
use App\Models\Awardee;
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

#[Name('search_awardees')]
#[Title('Search awardees by name, award or posthumous status')]
#[Description('Search people awarded with Ukrainian state awards by presidential decrees. Pass any parts of a full name in Ukrainian (surname, first name, and/or patronymic) in any order: matching ignores case, the grammatical case and the apostrophe style, so "Шевченко Тарас", "шевченка" and "Тараса Шевченка" find the same records. Optionally narrow the result by award ("орден за мужність", "медаль за військову службу", "орден Богдана Хмельницького") and by posthumous status. At least one criterion is required. Each row is one award, so a person awarded several times has several rows. Use it to answer whether a person was awarded, or who received a specific award. Ignore generic wording such as "державні нагороди" or "відзнаки" and do not pass it as the award. If nothing matches, verify the spelling or search by the surname only.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchAwardeesTool extends AwardeeTool
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 50;

    private const MAX_PAGE = 200;

    private const KNOWN_AWARDS_IN_ERROR = 5;

    public function __construct(
        private readonly AwardeeSearch $awardees,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'award' => ['nullable', 'string', 'max:200'],
            'is_posthumous' => ['nullable', 'boolean'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
        ], [
            'limit.max' => 'At most '.self::MAX_LIMIT.' records can be returned in one call.',
            'page.max' => 'At most '.self::MAX_PAGE.' pages can be requested in one call.',
        ]);

        $query = trim((string) ($validated['query'] ?? ''));
        $awardQuery = trim((string) ($validated['award'] ?? ''));
        $isPosthumous = ($validated['is_posthumous'] ?? null) !== null
            ? (bool) $validated['is_posthumous']
            : null;
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $page = (int) ($validated['page'] ?? 1);

        if ($query !== '' && mb_strlen($query, 'UTF-8') < 2) {
            return Response::error('Запит ПІБ має містити щонайменше два символи. Передайте прізвище, ім\'я або по батькові.');
        }

        if ($this->tokenCount($query) > self::MAX_NAME_TOKENS) {
            return Response::error('Запит ПІБ надто широкий. Передайте не більше '.self::MAX_NAME_TOKENS.' слів у query.');
        }

        if ($this->tokenCount($awardQuery) > self::MAX_AWARD_TOKENS) {
            return Response::error('Фраза нагороди надто широка. Передайте не більше '.self::MAX_AWARD_TOKENS.' слів у award.');
        }

        if ($query === '' && $awardQuery === '' && $isPosthumous === null) {
            return Response::error('Вкажіть хоча б один критерій: query (ПІБ), award (нагорода) або is_posthumous (посмертне нагородження).');
        }

        $awards = $awardQuery === '' ? collect() : $this->awardees->matchingAwards($awardQuery);

        if ($awardQuery !== '' && $awards->isEmpty()) {
            $knownAwards = $this->awardNames(
                $this->awardees->matchingAwards('')->take(self::KNOWN_AWARDS_IN_ERROR),
            );

            return Response::error($this->unknownAwardMessage($awardQuery, $knownAwards));
        }

        $matchedAwards = $this->awardNames($awards);
        /** @var list<int> $awardIds */
        $awardIds = array_values($awards->pluck('id')->map(fn (int|string $id): int => (int) $id)->all());
        $filters = $this->filtersPayload($awardQuery, $matchedAwards, $isPosthumous);

        $paginator = $this->awardees->searchByName(
            $query,
            $awardIds,
            $isPosthumous,
            $limit,
            $page,
        );

        $rows = array_map(
            fn (Awardee $awardee): array => $this->awardeeRow($awardee),
            array_values($paginator->items()),
        );

        $total = $paginator->total();
        $returned = count($rows);
        $lastPage = $paginator->lastPage();

        $structured = [
            'query' => $query,
            'total' => $total,
            'returned' => $returned,
            'page' => $page,
            'last_page' => $lastPage,
            'filters' => $filters,
            'awardees' => $rows,
        ];

        $criteria = [];

        if ($query !== '') {
            $criteria[] = "ПІБ містить «{$query}»";
        }

        if ($matchedAwards !== []) {
            $criteria[] = 'нагорода: '.implode('; ', $matchedAwards);
        }

        if ($isPosthumous !== null) {
            $criteria[] = $isPosthumous ? 'посмертне нагородження' : 'нагородження за життя';
        }

        $criteriaText = implode('; ', $criteria);

        if ($total === 0) {
            return Response::make(Response::text("За запитом ({$criteriaText}) нагороджених не знайдено."))
                ->withStructuredContent($structured);
        }

        if ($returned === 0) {
            return Response::make(Response::text("Сторінка {$page} порожня: за запитом ({$criteriaText}) всього знайдено записів: {$total} (остання сторінка — {$lastPage})."))
                ->withStructuredContent($structured);
        }

        $text = "За запитом ({$criteriaText}) знайдено нагороджень: {$total}. Показано: {$returned} (сторінка {$page} із {$lastPage}).";

        if ($page < $lastPage) {
            $text .= ' Щоб отримати наступні записи, передайте більший page, а щоб звузити результат — уточніть ПІБ (query), нагороду (award) або статус (is_posthumous).';
        }

        return Response::make(Response::text($text."\n\n".$this->awardeesTable($rows)))
            ->withStructuredContent($structured);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Name parts in Ukrainian: surname, first name and/or patronymic, in any order and any grammatical case. For example "Шевченко Тарас" or "шевченка". Use up to '.self::MAX_NAME_TOKENS.' words.')
                ->nullable(),
            'award' => $schema->string()
                ->description('Award phrase in Ukrainian, as the user said it: "орден за мужність", "медаль за військову службу Україні", "орден Богдана Хмельницького ІІІ ступеня". Quotes, case and the degree wording do not matter. Use up to '.self::MAX_AWARD_TOKENS.' words. Omit it for generic "державні нагороди".')
                ->nullable(),
            'is_posthumous' => $schema->boolean()
                ->description('Set true to return only posthumous awards, false to return only awards conferred during life. Omit it to return both.')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of award records to return (1-'.self::MAX_LIMIT.'). Defaults to '.self::DEFAULT_LIMIT.'.')
                ->min(1)
                ->max(self::MAX_LIMIT)
                ->default(self::DEFAULT_LIMIT),
            'page' => $schema->integer()
                ->description('Page of the results to return (1-'.self::MAX_PAGE.'). Use it to list all the records of a popular award page by page.')
                ->min(1)
                ->max(self::MAX_PAGE)
                ->default(1),
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
            'query' => $schema->string()
                ->description('The name query that was searched for; an empty string when the search used filters only.')
                ->required(),
            'total' => $schema->integer()
                ->description('Total number of award records that match the request.')
                ->required(),
            'returned' => $schema->integer()
                ->description('Number of award records in this response.')
                ->required(),
            'page' => $schema->integer()
                ->description('Page of the results in this response.')
                ->required(),
            'last_page' => $schema->integer()
                ->description('Number of the last page of the results.')
                ->required(),
            ...$this->filtersOutputSchema($schema),
            ...$this->awardeesOutputSchema($schema),
        ];
    }
}
