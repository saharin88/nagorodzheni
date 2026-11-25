<?php

namespace App\Mcp\Tools;

use App\Models\Award;
use App\Models\Awardee;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Tool;

/**
 * Shared presentation of the award records used by the awardee tools.
 */
abstract class AwardeeTool extends Tool
{
    protected const MAX_NAME_TOKENS = 6;

    protected const MAX_AWARD_TOKENS = 12;

    /**
     * Map an award record to the structured representation of a table row.
     *
     * @return array{
     *     id: int,
     *     full_name: string,
     *     full_name_nominative: string|null,
     *     rank: string,
     *     is_posthumous: bool,
     *     award: string,
     *     decree_number: string,
     *     decree_date: string,
     *     decree_url: string
     * }
     */
    protected function awardeeRow(Awardee $awardee): array
    {
        return [
            'id' => (int) $awardee->getKey(),
            'full_name' => $awardee->full_name,
            'full_name_nominative' => $awardee->full_name_nominative,
            'rank' => $awardee->rank,
            'is_posthumous' => $awardee->is_posthumous,
            'award' => $awardee->award->name,
            'decree_number' => $awardee->decree->number,
            'decree_date' => $awardee->decree->date->toDateString(),
            'decree_url' => $awardee->decree->url,
        ];
    }

    /**
     * Render the award records as a Markdown table.
     *
     * The columns follow the awardees table of the user panel: rank, full name
     * as written in the decree (genitive), award with the posthumous marker
     * appended, decree number and decree date. The nominative name and the
     * record id stay in the structured content only, because the panel hides
     * them as well. Unlike the panel, the rank is never truncated.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function awardeesTable(array $rows): string
    {
        $lines = [
            '| '.__('Rank').' | '.__('Awardee full name').' | '.__('Award').' | '.__('Decree number').' | '.__('Decree date').' |',
            '|:---:|---|---|:---:|:---:|',
        ];

        foreach ($rows as $row) {
            $award = (string) ($row['award'] ?? '');

            if ($row['is_posthumous'] === true) {
                $award = trim($award.' '.__('(posthumous)'));
            }

            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $this->cell((string) $row['rank']),
                $this->cell((string) $row['full_name']),
                $this->cell($award),
                $this->cell((string) $row['decree_number']),
                CarbonImmutable::parse((string) $row['decree_date'])->format('d.m.Y'),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Get the output schema property describing the award records.
     *
     * @return array<string, Type>
     */
    protected function awardeesOutputSchema(JsonSchema $schema): array
    {
        return [
            'awardees' => $schema->array()
                ->description('Award records matching the request.')
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'full_name' => $schema->string()
                        ->description('Full name as written in the decree (genitive case).')
                        ->required(),
                    'full_name_nominative' => $schema->string()
                        ->description('Full name in the nominative case, when known.')
                        ->nullable(),
                    'rank' => $schema->string()
                        ->description('Rank or position as written in the decree.')
                        ->required(),
                    'is_posthumous' => $schema->boolean()
                        ->description('Whether the award was conferred posthumously.')
                        ->required(),
                    'award' => $schema->string()
                        ->description('State award as written in the decree.')
                        ->required(),
                    'decree_number' => $schema->string()
                        ->description('Number of the decree that conferred the award.')
                        ->required(),
                    'decree_date' => $schema->string()
                        ->description('Date of the decree in the YYYY-MM-DD format.')
                        ->required(),
                    'decree_url' => $schema->string()
                        ->description('URL of the decree on the President of Ukraine website.')
                        ->required(),
                ]))
                ->required(),
        ];
    }

    /**
     * Describe the filters that were applied to a response.
     *
     * @param  list<string>  $matchedAwards  the award titles the award phrase resolved to
     * @return array{award: string|null, matched_awards: list<string>, is_posthumous: bool|null}
     */
    protected function filtersPayload(string $awardQuery, array $matchedAwards, ?bool $isPosthumous): array
    {
        return [
            'award' => $awardQuery === '' ? null : $awardQuery,
            'matched_awards' => $matchedAwards,
            'is_posthumous' => $isPosthumous,
        ];
    }

    /**
     * Get the output schema property describing the applied filters.
     *
     * @return array<string, Type>
     */
    protected function filtersOutputSchema(JsonSchema $schema): array
    {
        return [
            'filters' => $schema->object([
                'award' => $schema->string()
                    ->description('The award phrase the records were filtered by, as it was given.')
                    ->nullable(),
                'matched_awards' => $schema->array()
                    ->description('The award titles the phrase resolved to.')
                    ->items($schema->string())
                    ->required(),
                'is_posthumous' => $schema->boolean()
                    ->description('Whether the records were limited to posthumous (true) or living (false) awards.')
                    ->nullable(),
            ])->required(),
        ];
    }

    /**
     * Map an award to the row of the awards summary, in the panel layout.
     *
     * The row carries a ready-made "Нагороджені" label ("280 (184 посмертно)"),
     * so a consumer can render the panel column verbatim instead of composing
     * the posthumous part itself and risking a different layout.
     *
     * @return array{name: string, awardees_count: int, posthumous_awardees_count: int, awardees_label: string}
     */
    protected function awardRow(Award $award): array
    {
        $awardeesCount = (int) ($award->awardees_count ?? 0);
        $posthumousCount = (int) ($award->posthumous_awardees_count ?? 0);

        return [
            'name' => $award->name,
            'awardees_count' => $awardeesCount,
            'posthumous_awardees_count' => $posthumousCount,
            'awardees_label' => $this->awardeesCountLabel($awardeesCount, $posthumousCount),
        ];
    }

    /**
     * Build the "Нагороджені" cell of the panel: the total with the posthumous part in brackets.
     */
    protected function awardeesCountLabel(int $awardeesCount, int $posthumousCount): string
    {
        if ($posthumousCount === 0) {
            return (string) $awardeesCount;
        }

        return $awardeesCount.' '.__('(:count posthumous)', ['count' => $posthumousCount]);
    }

    /**
     * Render the award titles as a Markdown table.
     *
     * The columns mirror the awards table of the panel exactly, so an answer
     * built from this table looks like the panel the user sees: the award
     * title first, then the total number of awardees as a single cell with
     * the posthumous part in brackets ("10 (3 посмертно)"). The posthumous
     * count is never a column of its own.
     *
     * @param  array<int, array{name: string, awardees_count: int, posthumous_awardees_count: int}>  $awards
     */
    protected function awardsTable(array $awards): string
    {
        $lines = [
            '| '.__('Award name').' | '.__('Awardees').' |',
            '|---|:---:|',
        ];

        foreach ($awards as $award) {
            $lines[] = sprintf(
                '| %s | %s |',
                $this->cell($award['name']),
                $this->awardeesCountLabel($award['awardees_count'], $award['posthumous_awardees_count']),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Get the output schema property describing the award breakdown of a decree.
     *
     * The breakdown is what makes a single call answer "which awards does this
     * decree confer, how many awardees and how many of them posthumously".
     *
     * @return array<string, Type>
     */
    protected function awardsBreakdownOutputSchema(JsonSchema $schema): array
    {
        return [
            'awards' => $schema->array()
                ->description('Every award of the decree in the panel layout: the title, the counts and the ready-to-render "Awardees" label. Present only when the request is not narrowed by an award.')
                ->items($schema->object($this->awardsBreakdownRowOutputSchema($schema))),
        ];
    }

    /**
     * Get the output schema property describing one row of the award summary.
     *
     * @return array{name: Type, awardees_count: Type, posthumous_awardees_count: Type, awardees_label: Type}
     */
    protected function awardsBreakdownRowOutputSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Award title exactly as it is stored in the registry.')
                ->required(),
            'awardees_count' => $schema->integer()
                ->description('Total number of award records of this title.')
                ->required(),
            'posthumous_awardees_count' => $schema->integer()
                ->description('Number of posthumous award records of this title.')
                ->required(),
            'awardees_label' => $schema->string()
                ->description('Ready-to-render "Awardees" cell of the panel, e.g. "280 (184 посмертно)". Use it as the value of the awardees column instead of composing the posthumous part yourself.')
                ->required(),
        ];
    }

    /**
     * Build the error message used when no award title matches a phrase.
     *
     * @param  list<string>  $knownAwardNames  a few existing titles offered as the way forward
     */
    protected function unknownAwardMessage(string $awardQuery, array $knownAwardNames): string
    {
        $message = "Жодна нагорода не відповідає «{$awardQuery}».";

        if ($knownAwardNames !== []) {
            $message .= ' Наприклад, у реєстрі є: '.implode('; ', $knownAwardNames).'.';
        }

        return $message.' Викличте list_awards, щоб отримати повний перелік назв нагород.';
    }

    /**
     * Get the lowercased award titles of the given awards.
     *
     * @param  iterable<Award>  $awards
     * @return list<string>
     */
    protected function awardNames(iterable $awards): array
    {
        $names = [];

        foreach ($awards as $award) {
            $names[] = $award->name;
        }

        return $names;
    }

    /**
     * Escape a value for a Markdown table cell.
     */
    private function cell(string $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], $value);
    }

    /**
     * Count non-empty whitespace/comma separated tokens used in free-text filters.
     */
    protected function tokenCount(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        return count(array_filter(
            preg_split('/[\s,]+/u', $value) ?: [],
            static fn (string $token): bool => $token !== '',
        ));
    }
}
