<?php

namespace App\Services;

use App\Ai\Agents\UkrainianNameInflector;
use App\Contracts\AwardeeNameInflector;
use App\Exceptions\AwardeeNameInflectionException;
use Laravel\Ai\Responses\StructuredAgentResponse;

class AiAwardeeNameInflector implements AwardeeNameInflector
{
    private const string FROM_GENITIVE_INSTRUCTION = 'Постав кожне ПІБ у називний відмінок (хто? що?).';

    public function __construct(private readonly UkrainianNameInflector $agent) {}

    public function fromGenitive(string $genitiveFullName): string
    {
        $normalizedFullName = $this->normalizeFullName($genitiveFullName);

        if ($normalizedFullName === '') {
            return '';
        }

        return $this->fromGenitiveMany([$normalizedFullName])[$normalizedFullName] ?? '';
    }

    /**
     * @param  list<string>  $genitiveFullNames
     * @return array<string, string>
     */
    public function fromGenitiveMany(array $genitiveFullNames): array
    {
        $normalizedNames = array_map($this->normalizeFullName(...), $genitiveFullNames);
        $uniqueNames = $this->uniqueNames($normalizedNames);

        if ($uniqueNames === []) {
            return [];
        }

        return $this->requestNominativeNames($uniqueNames);
    }

    /**
     * Get the distinct names that should be sent to the agent, keeping their order.
     *
     * @param  list<string>  $genitiveFullNames
     * @return list<string>
     */
    private function uniqueNames(array $genitiveFullNames): array
    {
        $uniqueNames = [];
        $seenNames = [];

        foreach ($genitiveFullNames as $genitiveFullName) {
            if ($genitiveFullName === '' || isset($seenNames[$genitiveFullName])) {
                continue;
            }

            $seenNames[$genitiveFullName] = true;
            $uniqueNames[] = $genitiveFullName;
        }

        return $uniqueNames;
    }

    /**
     * @param  list<string>  $uniqueNames
     * @return array<string, string>
     */
    private function requestNominativeNames(array $uniqueNames): array
    {
        $response = $this->agent->prompt(self::FROM_GENITIVE_INSTRUCTION.PHP_EOL.PHP_EOL.$this->numberedList($uniqueNames));

        $entries = $response instanceof StructuredAgentResponse
            ? data_get($response->toArray(), 'names')
            : null;

        if (! is_array($entries)) {
            throw new AwardeeNameInflectionException(
                __('Unable to inflect the awardee names: the agent returned no names.')
            );
        }

        if (! array_is_list($entries)) {
            throw new AwardeeNameInflectionException(
                __('Unable to inflect the awardee names: the agent returned an unexpected entry.')
            );
        }

        if (count($entries) !== count($uniqueNames)) {
            throw new AwardeeNameInflectionException(
                __('Unable to inflect the awardee names: :returned of :expected names were returned.', [
                    'returned' => count($entries),
                    'expected' => count($uniqueNames),
                ])
            );
        }

        $nominativeNames = [];

        foreach ($entries as $position => $fullName) {
            $sourceName = $uniqueNames[$position];

            if (! is_string($fullName)) {
                throw new AwardeeNameInflectionException(
                    __('Unable to inflect the awardee names: the agent returned an unexpected entry.')
                );
            }

            $nominativeName = $this->normalizeFullName($fullName);

            if ($nominativeName === '') {
                throw new AwardeeNameInflectionException(
                    __('Unable to inflect the awardee name [:name].', ['name' => $sourceName])
                );
            }

            $nominativeNames[$sourceName] = $nominativeName;
        }

        return $nominativeNames;
    }

    /**
     * Get the names as a numbered list.
     *
     * @param  list<string>  $names
     */
    private function numberedList(array $names): string
    {
        $lines = [];

        foreach ($names as $index => $name) {
            $lines[] = ($index + 1).'. '.$name;
        }

        return implode(PHP_EOL, $lines);
    }

    private function normalizeFullName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
