<?php

namespace App\Services;

use App\Contracts\Contracts\AwardMerger as AwardMergerContract;
use App\Models\Award;
use App\Models\Awardee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AwardMerger implements AwardMergerContract
{
    /**
     * @param  Collection<int, Award>  $awards
     * @return Award the award the awardees were merged into
     *
     * @throws InvalidArgumentException when fewer than two awards are given
     */
    public function merge(Collection $awards): Award
    {
        $primaryAward = $this->getPrimaryAward($awards);

        $duplicateAwardIds = array_values(array_diff($awards->pluck('id')->all(), [$primaryAward->getKey()]));

        DB::transaction(function () use ($primaryAward, $duplicateAwardIds): void {
            Awardee::query()
                ->whereIn('award_id', $duplicateAwardIds)
                ->update(['award_id' => $primaryAward->getKey()]);

            Award::query()
                ->whereKey($duplicateAwardIds)
                ->delete();
        });

        return $primaryAward;
    }

    /**
     * Get the award with the most awardees out of the given ones.
     *
     * Awards that share the highest awardees count are resolved by the lowest
     * ID, so that the same selection always merges into the same award.
     *
     * @param  Collection<int, Award>  $awards
     *
     * @throws InvalidArgumentException when fewer than two awards are given
     */
    public function getPrimaryAward(Collection $awards): Award
    {
        if ($awards->count() < 2) {
            throw new InvalidArgumentException(__('Select at least two awards to merge.'));
        }

        return Award::query()
            ->whereKey($awards->pluck('id')->all())
            ->withCount('awardees')
            ->orderByDesc('awardees_count')
            ->orderBy('id')
            ->firstOrFail();
    }
}
