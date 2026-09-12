<?php

namespace App\Contracts\Contracts;

use App\Models\Award;
use App\Services\AwardMerger as AwardMergerService;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use InvalidArgumentException;

#[Bind(AwardMergerService::class)]
#[Singleton]
interface AwardMerger
{
    /**
     * Merge the given awards into the one with the most awardees.
     *
     * The awardees of the other awards are re-linked to the primary award,
     * then those duplicate awards are deleted.
     *
     * @param  Collection<int, Award>  $awards
     * @return Award the award the awardees were merged into
     *
     * @throws InvalidArgumentException when fewer than two awards are given
     */
    public function merge(Collection $awards): Award;

    /**
     * Get the award with the most awardees out of the given ones.
     *
     * @param  Collection<int, Award>  $awards
     *
     * @throws InvalidArgumentException when fewer than two awards are given
     */
    public function getPrimaryAward(Collection $awards): Award;
}
