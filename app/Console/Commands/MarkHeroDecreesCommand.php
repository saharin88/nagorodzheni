<?php

namespace App\Console\Commands;

use App\Contracts\DecreeMetaParser;
use App\Models\Decree;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('decrees:mark-hero')]
#[Description('Mark the stored decrees that confer the Hero of Ukraine title')]
class MarkHeroDecreesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DecreeMetaParser $decreeMetaParser): int
    {
        $marked = 0;
        $skipped = 0;

        Decree::query()
            ->where('is_hero', false)
            ->chunkById(100, function (Collection $decrees) use ($decreeMetaParser, &$marked, &$skipped): void {
                foreach ($decrees as $decree) {
                    try {
                        $isHero = $decreeMetaParser->isHeroDecree($decree->url);
                    } catch (Throwable $exception) {
                        $skipped++;

                        Log::error('Unable to detect the Hero of Ukraine decree: '.$exception->getMessage(), [
                            'decree_number' => $decree->number,
                            'decree_url' => $decree->url,
                            'exception' => $exception,
                        ]);

                        continue;
                    }

                    if (! $isHero) {
                        continue;
                    }

                    $decree->update(['is_hero' => true]);

                    $marked++;
                }
            });

        $this->info(__('Hero of Ukraine decrees marked: :count', ['count' => $marked]));

        if ($skipped > 0) {
            $this->error(__('Decrees skipped because of errors: :total', ['total' => $skipped]));
        }

        return self::SUCCESS;
    }
}
