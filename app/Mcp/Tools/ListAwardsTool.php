<?php

namespace App\Mcp\Tools;

use App\Contracts\AwardeeSearch;
use App\Models\Award;
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

#[Name('list_awards')]
#[Title('List the state awards of the registry')]
#[Description('List the state award titles that occur in the registry, with the number of award records for each. Use it when the wording of the user may map to several awards or to none, for example before filtering by "орден за мужність" or when the user asks which awards exist in the whole registry (for the awards of one decree use list_decree_awardees, whose "awards" breakdown covers the whole decree). Pass a phrase such as "орден", "медаль", "за мужність" or "княгині Ольги" to narrow the list; matching ignores quotes, case and the way the degree is written. Omit the phrase to list the most frequent awards.')]
#[IsReadOnly]
#[IsIdempotent]
class ListAwardsTool extends AwardeeTool
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly AwardeeSearch $awardees,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ], [
            'limit.max' => 'At most '.self::MAX_LIMIT.' awards can be returned in one call.',
        ]);

        $query = trim((string) ($validated['query'] ?? ''));
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);

        $awards = $this->awardees->matchingAwards($query);

        $rows = array_values(
            $awards
                ->take($limit)
                ->map(fn (Award $award): array => $this->awardRow($award))
                ->all(),
        );

        $total = $awards->count();
        $returned = count($rows);

        $structured = [
            'query' => $query,
            'total' => $total,
            'returned' => $returned,
            'awards' => $rows,
        ];

        if ($returned === 0) {
            return Response::make(Response::text("Нагород за запитом «{$query}» не знайдено."))
                ->withStructuredContent($structured);
        }

        $text = $query === ''
            ? "Нагород у реєстрі: {$total}. Показано: {$returned}."
            : "За запитом «{$query}» знайдено нагород: {$total}. Показано: {$returned}.";

        return Response::make(Response::text($text."\n\n".$this->awardsTable($rows)))
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
                ->description('A phrase to narrow the award titles, for example "орден", "медаль за військову службу" or "княгині Ольги". Omit it to list every award.')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximum number of awards to return (1-100). Defaults to 50.')
                ->min(1)
                ->max(self::MAX_LIMIT)
                ->default(self::DEFAULT_LIMIT),
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
                ->description('The phrase the titles were matched against; an empty string when every award was listed.')
                ->required(),
            'total' => $schema->integer()
                ->description('Number of award titles that match the phrase.')
                ->required(),
            'returned' => $schema->integer()
                ->description('Number of award titles in this response.')
                ->required(),
            'awards' => $schema->array()
                ->description('Award titles with the number of award records that use them, in the panel layout.')
                ->items($schema->object($this->awardsBreakdownRowOutputSchema($schema)))
                ->required(),
        ];
    }
}
