<?php

namespace App\Services;

use App\Contracts\AwardDecreeListParser;
use App\Contracts\AwardDecreeSynchronizer as AwardDecreeSynchronizerContract;
use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use App\Models\Decree;
use Illuminate\Support\Facades\Log;
use Throwable;

class AwardDecreeSynchronizer implements AwardDecreeSynchronizerContract
{
    public function __construct(
        private readonly AwardDecreeListParser $listParser,
        private readonly DecreeMetaParser $metaParser,
        private readonly DecreeAwardeeImporter $awardeeImporter,
    ) {}

    /**
     * @return array{added: int, awardees: int, skipped: int}
     *
     * @throws DecreeParseException when the decree list cannot be read
     */
    public function sync(): array
    {
        $listUrl = $this->getDecreeListUrl();
        $decrees = $this->listParser->getDecrees($listUrl);

        if ($decrees === []) {
            throw new DecreeParseException(__('The decree list came back empty [:url].', ['url' => $listUrl]));
        }

        $storedNumbers = Decree::query()
            ->whereIn('number', array_column($decrees, 'number'))
            ->pluck('number')
            ->all();

        $added = 0;
        $awardees = 0;
        $skipped = 0;

        foreach ($decrees as $decree) {
            if (in_array($decree['number'], $storedNumbers, true)) {
                continue;
            }

            try {
                $awardees += $this->importDecree($decree['url']);
                $added++;
            } catch (Throwable $exception) {
                $skipped++;

                Log::error('Error during the award decree sync: '.$exception->getMessage(), [
                    'decree_number' => $decree['number'],
                    'decree_url' => $decree['url'],
                    'exception' => $exception,
                ]);
            }
        }

        return [
            'added' => $added,
            'awardees' => $awardees,
            'skipped' => $skipped,
        ];
    }

    /**
     * Store the decree the page belongs to and import the awardees it mentions.
     *
     * A decree whose awardees cannot be imported is removed again, so that the next
     * run retries it instead of leaving a decree without awardees behind.
     *
     * @return int the number of the imported awardees
     */
    private function importDecree(string $decreeUrl): int
    {
        $decree = Decree::query()->firstOrCreate([
            'number' => $this->metaParser->getDecreeNumber($decreeUrl),
        ], [
            'date' => $this->metaParser->getDecreeDate($decreeUrl),
            'url' => $decreeUrl,
        ]);

        try {
            return $this->awardeeImporter->import($decree);
        } catch (Throwable $exception) {
            if ($decree->wasRecentlyCreated) {
                $decree->delete();
            }

            throw $exception;
        }
    }

    private function getDecreeListUrl(): string
    {
        return 'https://www.president.gov.ua/documents/decrees?'.http_build_query([
            's-num' => '',
            'contain-rule' => 'contains',
            's-text' => $this->getSearchText(),
        ]);
    }

    /**
     * Get the search text that keeps only the decrees about state awards.
     *
     * The site searches its Ukrainian documents, so the phrase is always resolved
     * in Ukrainian, whatever locale the application runs in.
     */
    private function getSearchText(): string
    {
        return __('the search text of the decree list about state awards', locale: 'uk');
    }
}
