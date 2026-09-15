<?php

namespace App\Console\Commands;

use App\Contracts\AwardDecreeSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('decrees:sync')]
#[Description('Import the decrees about state awards that the President has published recently')]
class SyncAwardDecreesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AwardDecreeSynchronizer $synchronizer): int
    {
        $this->info(__('Looking for the new decrees about state awards...'));

        $result = $synchronizer->sync();

        $this->info(__('New decrees added: :count', ['count' => $result['added']]));
        $this->info(__('Imported awardees: :count', ['count' => $result['awardees']]));

        if ($result['skipped'] > 0) {
            $this->error(__('Decrees skipped because of errors: :total', ['total' => $result['skipped']]));
        }

        return self::SUCCESS;
    }
}
