<?php

namespace App\Services;

use App\Contracts\DecreeAwardeeParser;
use App\Jobs\InflectAwardeeNamesJob;
use App\Models\Award;
use App\Models\Decree;
use Illuminate\Support\Facades\DB;

class DecreeAwardeeImporter
{
    public function __construct(
        private readonly DecreeAwardeeParser $decreeAwardeeParser,
    ) {}

    public function import(Decree $decree): int
    {
        $parsedAwardees = $this->decreeAwardeeParser->getAwardees($decree->url);

        if ($parsedAwardees === []) {
            return 0;
        }

        DB::transaction(function () use ($decree, $parsedAwardees): void {
            /** @var array<string, int> $awardIdsByName */
            $awardIdsByName = [];

            foreach ($parsedAwardees as $parsedAwardee) {
                $awardName = $parsedAwardee['award'];

                if (! array_key_exists($awardName, $awardIdsByName)) {
                    $award = Award::query()->firstOrCreate([
                        'name' => $awardName,
                    ]);

                    $awardIdsByName[$awardName] = $award->getKey();
                }

                $decree->awardees()->firstOrCreate([
                    'full_name' => $parsedAwardee['full_name'],
                    'rank' => $parsedAwardee['rank'],
                    'award_id' => $awardIdsByName[$awardName],
                    'is_posthumous' => $parsedAwardee['is_posthumous'],
                ]);
            }
        });

        $decree->awardees()
            ->whereNull('full_name_nominative')
            ->pluck('id')
            ->chunk(1000)
            ->each(function ($chunk) {
                InflectAwardeeNamesJob::dispatch($chunk->all());
            });

        return count($parsedAwardees);
    }
}
