<?php

namespace App\Jobs;

use App\Contracts\AwardeeNameInflector;
use App\Models\Awardee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(630)]
#[Backoff([5 - 7])]
class InflectAwardeeNamesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int>  $awardeeIds
     */
    public function __construct(
        public array $awardeeIds
    ) {}

    public function handle(AwardeeNameInflector $awardeeNameInflector): void
    {
        if (empty($this->awardeeIds)) {
            return;
        }

        $awardees = Awardee::query()
            ->select(['id', 'full_name'])
            ->whereIn('id', $this->awardeeIds)
            ->get();

        if ($awardees->isEmpty()) {
            return;
        }

        /** @var list<string> $uniqueNames */
        $uniqueNames = $awardees->pluck('full_name')
            ->unique()
            ->values()
            ->all();

        $nominativeNamesByGenitive = $awardeeNameInflector->fromGenitiveMany($uniqueNames);

        foreach ($awardees as $awardee) {
            $fullNameNominative = $nominativeNamesByGenitive[$awardee->full_name] ?? null;

            if (blank($fullNameNominative)) {
                continue;
            }

            $awardee->full_name_nominative = $fullNameNominative;
            $awardee->save();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error(__('Failed to inflect awardee names'), [
            'awardee_ids' => $this->awardeeIds,
            'exception' => $exception,
        ]);
    }
}
