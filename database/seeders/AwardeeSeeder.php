<?php

namespace Database\Seeders;

use App\Models\Decree;
use App\Services\DecreeAwardeeImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Throwable;

class AwardeeSeeder extends Seeder
{
    private const int CHUNK_SIZE = 100;

    /**
     * Seed the awardees of the decrees that are already stored.
     *
     * Every decree is imported on its own: a decree whose page cannot be
     * fetched or parsed is logged and skipped instead of aborting the run.
     */
    public function run(DecreeAwardeeImporter $decreeAwardeeImporter): void
    {
        $this->command->info(__('Import of awardees started...'));

        $totalImported = 0;
        $totalSkipped = 0;

        Decree::query()
            ->lazyById(self::CHUNK_SIZE)
            ->each(function (Decree $decree) use ($decreeAwardeeImporter, &$totalImported, &$totalSkipped): void {
                try {
                    $totalImported += $decreeAwardeeImporter->import($decree);
                } catch (Throwable $exception) {
                    $totalSkipped++;

                    Log::error('Error during awardee import: '.$exception->getMessage(), [
                        'decree_id' => $decree->getKey(),
                        'decree_number' => $decree->number,
                        'exception' => $exception,
                    ]);

                    $this->command->warn(__('Unable to import the awardees of the decree :number: :message', [
                        'number' => $decree->number,
                        'message' => $exception->getMessage(),
                    ]));
                }
            });

        $this->command->info(__('Import completed successfully! Awardees added/updated: :total', ['total' => $totalImported]));

        if ($totalSkipped > 0) {
            $this->command->error(__('Decrees skipped because of errors: :total', ['total' => $totalSkipped]));
        }
    }
}
