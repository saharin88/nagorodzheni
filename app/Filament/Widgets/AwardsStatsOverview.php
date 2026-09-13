<?php

namespace App\Filament\Widgets;

use App\Models\Award;
use App\Models\Awardee;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class AwardsStatsOverview extends StatsOverviewWidget
{

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = [];

        foreach ($this->getAwardsWithAwardeeCounts() as $award) {
            $stat = Stat::make($award->name, (int) $award->awardees_count)
                ->url($this->getAwardeesUrl($award));

            if ($award->posthumous_awardees_count > 0) {
                $stat->description(__('(:count posthumous)', ['count' => $award->posthumous_awardees_count]));
            }

            $stats[] = $stat;
        }

        return $stats;
    }

    /**
     * @return Collection<int, Award>
     */
    protected function getAwardsWithAwardeeCounts(): Collection
    {
        $awardeeCounts = Awardee::query()
            ->selectRaw('award_id, COUNT(*) AS awardees_count, SUM(CASE WHEN is_posthumous THEN 1 ELSE 0 END) AS posthumous_awardees_count')
            ->whereNotNull('award_id')
            ->groupBy('award_id');

        return Award::query()
            ->leftJoinSub($awardeeCounts, 'awardee_counts', 'awards.id', '=', 'awardee_counts.award_id')
            ->select('awards.*')
            ->selectRaw('COALESCE(awardee_counts.awardees_count, 0) AS awardees_count')
            ->selectRaw('COALESCE(awardee_counts.posthumous_awardees_count, 0) AS posthumous_awardees_count')
            ->orderByDesc('awardees_count')
            ->orderBy('awards.name')
            ->get();
    }

    /**
     * Resolve the awardee list of the panel in use, so the widget is not tied to one panel.
     */
    protected function getAwardeesUrl(Award $award): ?string
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || $panel->getModelResource(Awardee::class) === null) {
            return null;
        }

        return $panel->getResourceUrl(Awardee::class, 'index', [
            'filters' => [
                'award' => [
                    'values' => [$award->getKey()],
                ],
            ],
        ]);
    }
}
