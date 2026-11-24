<?php

namespace App\Filament\Widgets;

use App\Models\Award;
use App\Models\Awardee;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AwardsStatsOverview extends StatsOverviewWidget
{
    public const string CACHE_KEY = 'filament.awards.stats-overview.v2';

    protected static ?bool $isLazyLoaded = false;

    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = [];

        foreach ($this->getAwardsWithAwardeeCounts() as $award) {
            $imageUrl = $this->getImageUrl($award['image_path']);

            $stat = Stat::make($award['name'], (int) $award['awardees_count'])
                ->view('filament.widgets.awards-stats-overview-stat', [
                    'awardImageUrl' => $imageUrl ? $imageUrl.'?v='.$award['updated_at'] : null,
                ])
                ->url($this->getAwardeesUrl((int) $award['id']));

            if ($award['posthumous_awardees_count'] > 0) {
                $stat->description(__('(:count posthumous)', ['count' => $award['posthumous_awardees_count']]));
            }

            $stats[] = $stat;
        }

        return $stats;
    }

    protected function getImageUrl(?string $imagePath): ?string
    {
        if (blank($imagePath)) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($imagePath)
            ? $disk->url($imagePath)
            : null;
    }

    /**
     * @return array<int, array{id: int, name: string, image_path: string|null, awardees_count: int, posthumous_awardees_count: int, updated_at: int}>
     */
    protected function getAwardsWithAwardeeCounts(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            $awardeeCounts = Awardee::query()
                ->selectRaw('award_id, COUNT(*) AS awardees_count, SUM(CASE WHEN is_posthumous THEN 1 ELSE 0 END) AS posthumous_awardees_count')
                ->whereNotNull('award_id')
                ->groupBy('award_id');

            return Award::query()
                ->leftJoinSub($awardeeCounts, 'awardee_counts', 'awards.id', '=', 'awardee_counts.award_id')
                ->select([
                    'awards.id',
                    'awards.name',
                    'awards.image_path',
                    'awards.updated_at',
                ])
                ->selectRaw('COALESCE(awardee_counts.awardees_count, 0) AS awardees_count')
                ->selectRaw('COALESCE(awardee_counts.posthumous_awardees_count, 0) AS posthumous_awardees_count')
                ->withCasts([
                    'updated_at' => 'timestamp',
                ])
                ->orderBy('awards.sort')
                ->orderBy('awards.name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Resolve the awardee list of the panel in use, so the widget is not tied to one panel.
     */
    protected function getAwardeesUrl(int $awardId): ?string
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || $panel->getModelResource(Awardee::class) === null) {
            return null;
        }

        return $panel->getResourceUrl(Awardee::class, 'index', [
            'filters' => [
                'award' => [
                    'values' => [$awardId],
                ],
            ],
        ]);
    }
}
