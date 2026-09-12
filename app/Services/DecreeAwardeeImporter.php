<?php

namespace App\Services;

use App\Contracts\Contracts\DecreeAwardeeParser;
use App\Models\Award;
use App\Models\Decree;
use Illuminate\Support\Facades\DB;

class DecreeAwardeeImporter
{
    public function __construct(
        private readonly DecreeAwardeeParser $decreeAwardeeParser,
    ) {}

    /**
     * Import the awardees of the decree together with their awards.
     *
     * @return int the number of the imported awardees
     */
    public function import(Decree $decree): int
    {
        $parsedAwardees = $this->decreeAwardeeParser->getAwardees($decree->url);

        if ($parsedAwardees === []) {
            return 0;
        }

        DB::transaction(function () use (
            $decree,
            $parsedAwardees,
        ): void {
            foreach ($parsedAwardees as $index => $parsedAwardee) {
                $award = Award::query()->firstOrCreate([
                    'name' => $parsedAwardee['award'],
                ]);

                $decree->awardees()->firstOrCreate([
                    'full_name' => $parsedAwardee['full_name'],
                    'rank' => $parsedAwardee['rank'],
                    'award_id' => $award->getKey(),
                ], [
                    'is_posthumous' => $parsedAwardee['is_posthumous'],
                ]);
            }
        });

        return count($parsedAwardees);
    }
}
