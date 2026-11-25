<?php

namespace App\Models\Concerns;

use App\Services\AwardeeSearch;
use Illuminate\Support\Facades\Cache;

trait FlushesAwardsCache
{
    protected static function bootFlushesAwardsCache(): void
    {
        $flush = static function (): void {
            Cache::forget(AwardeeSearch::AWARDS_CACHE_KEY);
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
