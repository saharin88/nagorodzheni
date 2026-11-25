<?php

namespace App\Services;

use App\Contracts\AwardeeSearch as AwardeeSearchContract;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AwardeeSearch implements AwardeeSearchContract
{
    /**
     * The cache key of the awards collection that carries the awardee counts.
     *
     * `Award` and `Awardee` invalidate it through model events (the
     * FlushesAwardsCache trait) on save and delete, so the next read rebuilds
     * the collection from the database. Mass writes issued on the query
     * builder (update/delete) bypass model events and must call
     * Cache::forget(AwardeeSearch::AWARDS_CACHE_KEY) themselves — as
     * AwardMerger does.
     */
    public const string AWARDS_CACHE_KEY = 'mcp.awards.with_counts';

    /**
     * The decree number as it is stored, with an optional year: "1131/2026".
     */
    private const DECREE_NUMBER_PATTERN = '/(?<ordinal>\d+)(?:\s*[\/\-–—]\s*(?<year>\d{4}))?/u';

    /**
     * Words that only mark the degree of an award, so they are ignored while matching.
     *
     * @var list<string>
     */
    private const DEGREE_WORDS = ['ступеня', 'ступінь', 'ступені', 'ступенів', 'ступень'];

    /**
     * Ordinal words that name the degree of an award, without apostrophes.
     *
     * @var array<string, int>
     */
    private const ORDINAL_WORDS = [
        'першого' => 1, 'перший' => 1, 'перша' => 1, 'перше' => 1, 'перші' => 1,
        'другого' => 2, 'другий' => 2, 'друга' => 2, 'друге' => 2, 'другі' => 2,
        'третього' => 3, 'третій' => 3, 'третя' => 3, 'третє' => 3, 'треті' => 3,
        'четвертого' => 4, 'четвертий' => 4, 'четверта' => 4, 'четверте' => 4,
        'пятого' => 5, 'пятий' => 5, 'пята' => 5, 'пяте' => 5,
        'шостого' => 6, 'шостий' => 6,
        'сьомого' => 7, 'сьомий' => 7,
        'восьмого' => 8, 'восьмий' => 8,
        'девятого' => 9, 'девятий' => 9,
        'десятого' => 10, 'десятий' => 10,
    ];

    /**
     * Words of at most this length must match a whole word of the award title.
     */
    private const SHORT_TOKEN_LENGTH = 3;

    /**
     * Longer words are compared by this many leading letters, so "медаль"
     * finds "медаллю" and "орден" finds "орденом".
     */
    private const STEM_LENGTH = 5;

    public function searchByName(string $query, array $awardIds = [], ?bool $isPosthumous = null, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $tokens = $this->nameTokens($query);
        $hasFilters = $awardIds !== [] || $isPosthumous !== null;

        return Awardee::query()
            ->select('awardees.*')
            ->join('decrees', 'decrees.id', '=', 'awardees.decree_id')
            ->with(['award', 'decree'])
            ->when($tokens === [] && ! $hasFilters, fn (Builder $builder): Builder => $builder->whereRaw('1 = 0'))
            ->when($tokens !== [], function (Builder $builder) use ($tokens): void {
                foreach ($tokens as $token) {
                    $this->whereNameMatches($builder, $this->likePattern($token));
                }
            })
            ->when($awardIds !== [], fn (Builder $builder): Builder => $builder->whereIn('awardees.award_id', $awardIds))
            ->when($isPosthumous !== null, fn (Builder $builder): Builder => $builder->where('awardees.is_posthumous', $isPosthumous))
            ->orderByDesc('decrees.date')
            ->orderBy('awardees.full_name')
            ->paginate($perPage, page: $page);
    }

    /**
     * Find the decrees whose number matches the given one.
     *
     * @return Collection<int, Decree>
     */
    public function findDecrees(string $number): Collection
    {
        if (preg_match(self::DECREE_NUMBER_PATTERN, $number, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            return collect();
        }

        $ordinal = (int) $matches['ordinal'];
        $year = $matches['year'] ?? null;

        $query = Decree::query()
            ->withCount('awardees')
            ->orderByDesc('date');

        if ($year !== null) {
            return $query->where('number', "{$ordinal}/{$year}")->get();
        }

        /** @var Collection<int, Decree> $decrees */
        $decrees = $query
            ->where('number', 'like', "{$ordinal}/%")
            ->get()
            ->filter(fn (Decree $decree): bool => $this->ordinalOf($decree) === $ordinal)
            ->values();

        return $decrees;
    }

    /**
     * Find the newest decree, optionally the newest one that mentions the given awards.
     *
     * @param  list<int>  $awardIds
     */
    public function findLatestDecree(array $awardIds = [], ?bool $isPosthumous = null): ?Decree
    {
        $isFiltered = $awardIds !== [] || $isPosthumous !== null;

        return Decree::query()
            ->withCount('awardees')
            ->when($isFiltered, fn (Builder $builder): Builder => $builder->whereHas(
                'awardees',
                function (Builder $query) use ($awardIds, $isPosthumous): void {
                    $query
                        ->when($awardIds !== [], fn (Builder $query): Builder => $query->whereIn('award_id', $awardIds))
                        ->when($isPosthumous !== null, fn (Builder $query): Builder => $query->where('is_posthumous', $isPosthumous));
                },
            ))
            ->orderByDesc('date')
            ->orderByNumber('desc')
            ->first();
    }

    /**
     * Paginate the awardees of the given decree in the order they appear in it.
     *
     * @param  list<int>  $awardIds
     * @return LengthAwarePaginator<int, Awardee>
     */
    public function paginateByDecree(Decree $decree, array $awardIds = [], ?bool $isPosthumous = null, int $perPage = 100, int $page = 1): LengthAwarePaginator
    {
        return $decree->awardees()
            ->with(['award', 'decree'])
            ->when($awardIds !== [], fn (Builder $builder): Builder => $builder->whereIn('award_id', $awardIds))
            ->when($isPosthumous !== null, fn (Builder $builder): Builder => $builder->where('is_posthumous', $isPosthumous))
            ->orderBy('id')
            ->paginate($perPage, page: $page);
    }

    /**
     * Group the award records of one decree by award.
     *
     * One aggregate query answers what a caller would otherwise ask once per
     * award: the count of the records and the posthumous part of it. The
     * awards are ordered like the awards tables of the panels.
     *
     * @return Collection<int, Award>
     */
    public function awardBreakdownByDecree(Decree $decree): Collection
    {
        $counts = Awardee::query()
            ->selectRaw('award_id, COUNT(*) AS awardees_count, SUM(CASE WHEN is_posthumous THEN 1 ELSE 0 END) AS posthumous_awardees_count')
            ->where('decree_id', $decree->getKey())
            ->groupBy('award_id');

        return Award::query()
            ->joinSub($counts, 'decree_counts', 'awards.id', '=', 'decree_counts.award_id')
            ->select(['awards.id', 'awards.name'])
            ->selectRaw('decree_counts.awardees_count AS awardees_count')
            ->selectRaw('COALESCE(decree_counts.posthumous_awardees_count, 0) AS posthumous_awardees_count')
            ->orderBy('awards.sort')
            ->orderBy('awards.name')
            ->get();
    }

    /**
     * Resolve the awards whose title matches every word of the given phrase.
     *
     * The award list is small (a few dozen titles), so the titles are fetched
     * once and matched in PHP: that allows normalizing the degree notation
     * ("ІІІ", "III", "третього" and "3" all become "3") which SQL cannot do
     * for the Cyrillic roman numerals the decrees actually use.
     *
     * @return Collection<int, Award>
     */
    public function matchingAwards(string $query): Collection
    {
        $tokens = $this->awardTokens($query);

        /** @var Collection<int, Award> $all */
        $all = Cache::rememberForever(self::AWARDS_CACHE_KEY, fn (): Collection => Award::query()
            ->withCount([
                'awardees',
                'awardees as posthumous_awardees_count' => fn (Builder $builder): Builder => $builder->where('is_posthumous', true),
            ])
            ->get());

        return $all
            ->filter(fn (Award $award): bool => $this->awardMatches($award->name, $tokens))
            ->sortByDesc('awardees_count')
            ->values();
    }

    /**
     * Match the award title against the normalized words of the query.
     *
     * @param  list<string>  $tokens
     */
    private function awardMatches(string $awardName, array $tokens): bool
    {
        if ($tokens === []) {
            return true;
        }

        $awardTokens = $this->awardTokens($awardName);

        foreach ($tokens as $token) {
            if (! $this->awardTokenMatches($token, $awardTokens)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match one normalized word of the query against the words of an award title.
     *
     * Short words (including the degree digits) must match a whole word, so
     * "за" does not match "заслуги". Longer words are compared by their stem,
     * which absorbs the case ending the decrees use ("орденом", "медаллю").
     *
     * @param  list<string>  $awardTokens
     */
    private function awardTokenMatches(string $token, array $awardTokens): bool
    {
        if (mb_strlen($token, 'UTF-8') <= self::SHORT_TOKEN_LENGTH) {
            return in_array($token, $awardTokens, true);
        }

        $stem = mb_substr($token, 0, self::STEM_LENGTH, 'UTF-8');

        foreach ($awardTokens as $awardToken) {
            if (mb_strlen($awardToken, 'UTF-8') > self::SHORT_TOKEN_LENGTH && str_starts_with($awardToken, $stem)) {
                return true;
            }
        }

        return str_contains(implode(' ', $awardTokens), $token);
    }

    /**
     * Normalize an award title or an award phrase into comparable words.
     *
     * Quotes and punctuation separate the words, every apostrophe style is
     * dropped, the degree words are ignored and a degree written as a roman
     * numeral or as an ordinal word becomes an arabic digit.
     *
     * @return list<string>
     */
    private function awardTokens(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['’', 'ʼ', '`', '‘', '´', "'"], '', $text);
        $text = str_replace(
            ['«', '»', '“', '”', '„', '"', '(', ')', '[', ']', '{', '}', '№', '#', '.', ',', ';', ':', '!', '?', '/', '\\', '—', '–', '-', '+', '*'],
            ' ',
            $text,
        );

        $tokens = preg_split('/\s+/u', trim($text)) ?: [];

        $normalized = [];

        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, self::DEGREE_WORDS, true)) {
                continue;
            }

            $normalized[] = (string) ($this->ordinalValue($token) ?? $token);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Get the numeric value of a degree written as an ordinal word or a roman numeral.
     *
     * The decrees use Cyrillic "І" (U+0406) in some titles and the Latin "I" in
     * others, so both spellings are treated as the same numeral.
     */
    private function ordinalValue(string $token): ?int
    {
        if (isset(self::ORDINAL_WORDS[$token])) {
            return self::ORDINAL_WORDS[$token];
        }

        $roman = strtr($token, ['і' => 'i', 'ı' => 'i']);

        if ($roman === '' || preg_match('/^x{0,3}(ix|iv|v?i{0,3})$/', $roman) !== 1) {
            return null;
        }

        return $this->romanToInt($roman);
    }

    /**
     * Convert a roman numeral built of i, v and x into a number.
     */
    private function romanToInt(string $roman): int
    {
        $values = ['i' => 1, 'v' => 5, 'x' => 10];
        $total = 0;
        $previous = 0;

        foreach (array_reverse(str_split($roman)) as $character) {
            $value = $values[$character];
            $total += $value < $previous ? -$value : $value;
            $previous = max($previous, $value);
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $query): array
    {
        $query = mb_strtolower(trim($query), 'UTF-8');
        $query = str_replace(['’', 'ʼ', '`', '‘', '´', "'"], '', $query);

        $tokens = preg_split('/[\s,]+/u', $query) ?: [];

        $tokens = array_map(
            fn (string $token): string => trim($token, '.,;:()[]«»"'),
            $tokens,
        );

        $tokens = array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token, 'UTF-8') >= 2,
        );

        return array_values(array_unique($tokens));
    }

    private function likePattern(string $token): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token).'%';
    }

    /**
     * Add the condition matching one name part against both name forms.
     *
     * The SQL is kept as a literal fragment because the case insensitive
     * expression depends on the database driver: SQLite only folds ASCII
     * characters in LOWER() and LIKE, so the Cyrillic aware ukrainian_search()
     * function (lowercase without apostrophes) is registered in AppServiceProvider.
     *
     * @param  Builder<Awardee>  $builder
     */
    private function whereNameMatches(Builder $builder, string $pattern): void
    {
        $sql = match (DB::connection()->getDriverName()) {
            'sqlite' => "(ukrainian_search(full_name) LIKE ? ESCAPE '\\' OR ukrainian_search(full_name_nominative) LIKE ? ESCAPE '\\')",
            'pgsql' => "(REPLACE(REPLACE(REPLACE(LOWER(CAST(full_name AS TEXT)), '''', ''), '’', ''), 'ʼ', '') LIKE ? ESCAPE '\\' OR REPLACE(REPLACE(REPLACE(LOWER(CAST(full_name_nominative AS TEXT)), '''', ''), '’', ''), 'ʼ', '') LIKE ? ESCAPE '\\')",
            default => "(REPLACE(REPLACE(REPLACE(LOWER(full_name), '''', ''), '’', ''), 'ʼ', '') LIKE ? ESCAPE '\\' OR REPLACE(REPLACE(REPLACE(LOWER(full_name_nominative), '''', ''), '’', ''), 'ʼ', '') LIKE ? ESCAPE '\\')",
        };

        $builder->whereRaw($sql, [$pattern, $pattern]);
    }

    /**
     * Get the ordinal part of the decree number ("1131" for "1131/2026").
     */
    private function ordinalOf(Decree $decree): int
    {
        return (int) strtok($decree->number, '/');
    }
}
