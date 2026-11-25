<?php

namespace App\Contracts;

use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use App\Services\AwardeeSearch as AwardeeSearchService;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

#[Bind(AwardeeSearchService::class)]
#[Singleton]
interface AwardeeSearch
{
    /**
     * Search the award records by the parts of an awardee name.
     *
     * Every whitespace separated part of the query must occur in either the
     * genitive (decree) or the nominative form of the name, so the parts may be
     * given in any order, in any grammatical case and with any apostrophe.
     *
     * @param  list<int>  $awardIds  restrict the records to these awards; an empty list means no restriction
     * @param  bool|null  $isPosthumous  restrict the records to posthumous (true) or living (false) awards
     * @return LengthAwarePaginator<int, Awardee>
     */
    public function searchByName(string $query, array $awardIds = [], ?bool $isPosthumous = null, int $perPage = 25, int $page = 1): LengthAwarePaginator;

    /**
     * Find the decrees whose number matches the given one.
     *
     * The year may be omitted, in which case decrees of several years can match.
     * The decrees are ordered by date, the newest first.
     *
     * @return Collection<int, Decree>
     */
    public function findDecrees(string $number): Collection;

    /**
     * Find the newest decree, optionally the newest one that mentions the given awards.
     *
     * The decree date is compared first and the ordinal of the number second,
     * because several decrees can share a date numbering restarts every year.
     *
     * @param  list<int>  $awardIds  restrict the search to the decrees that confer these awards
     * @param  bool|null  $isPosthumous  restrict the search to the decrees that have posthumous (true) or living (false) awards
     */
    public function findLatestDecree(array $awardIds = [], ?bool $isPosthumous = null): ?Decree;

    /**
     * Paginate the awardees of the given decree in the order they appear in it.
     *
     * @param  list<int>  $awardIds  restrict the records to these awards; an empty list means no restriction
     * @return LengthAwarePaginator<int, Awardee>
     */
    public function paginateByDecree(Decree $decree, array $awardIds = [], ?bool $isPosthumous = null, int $perPage = 100, int $page = 1): LengthAwarePaginator;

    /**
     * Group the award records of one decree by award.
     *
     * The breakdown always covers the whole decree, so "which awards does this
     * decree confer and how many of them are posthumous" is answered in one
     * call instead of one call per award. The awards keep the panel order.
     *
     * @return Collection<int, Award> the awards with awardees_count and posthumous_awardees_count
     */
    public function awardBreakdownByDecree(Decree $decree): Collection;

    /**
     * Resolve the awards whose title matches every word of the given phrase.
     *
     * The matching ignores the case, the quotes, the apostrophe style, the
     * grammatical case of the words and the way the degree is written, so
     * "орден за мужність", "орден «За мужність» ІІІ ступеня" and
     * "орден за мужність третього ступеня" all resolve to the same titles.
     * An empty phrase resolves to every award.
     *
     * @return Collection<int, Award>
     */
    public function matchingAwards(string $query): Collection;
}
