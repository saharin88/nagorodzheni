<?php

namespace App\Services;

use App\Ai\Agents\UkrainianNameInflector;
use App\Contracts\Contracts\AwardeeNameInflector;
use App\Exceptions\AwardeeNameInflectionException;
use Laravel\Ai\Responses\StructuredAgentResponse;

class AiAwardeeNameInflector implements AwardeeNameInflector
{
    private const string TO_GENITIVE_INSTRUCTION = 'Постав кожне ПІБ у родовий відмінок (кого? чого?).';

    private const string FROM_GENITIVE_INSTRUCTION = 'Постав кожне ПІБ у називний відмінок (хто? що?).';

    public function __construct(private readonly UkrainianNameInflector $agent) {}

    public function toGenitive(string $fullName): string
    {
        return $this->toGenitiveMany([$fullName])[0];
    }

    public function fromGenitive(string $genitiveFullName): string
    {
        return $this->fromGenitiveMany([$genitiveFullName])[0];
    }

    public function toGenitiveMany(array $fullNames): array
    {
        return $this->inflectMany($fullNames, self::TO_GENITIVE_INSTRUCTION);
    }

    public function fromGenitiveMany(array $genitiveFullNames): array
    {
        return $this->inflectMany($genitiveFullNames, self::FROM_GENITIVE_INSTRUCTION);
    }

    /**
     * Inflect every given name with a single agent request.
     *
     * @param  list<string>  $fullNames
     * @return list<string>
     */
    private function inflectMany(array $fullNames, string $instruction): array
    {
        $normalizedNames = array_map($this->normalizeFullName(...), $fullNames);

        $uniqueNames = $this->uniqueNames($normalizedNames);

        if ($uniqueNames === []) {
            return $normalizedNames;
        }

        $inflectedNames = $this->requestInflectedNames($instruction, $uniqueNames);

        return array_map(
            fn (string $fullName): string => $inflectedNames[$fullName] ?? '',
            $normalizedNames,
        );
    }

    /**
     * Get the distinct names that should be sent to the agent, keeping their order.
     *
     * @param  list<string>  $fullNames
     * @return list<string>
     */
    private function uniqueNames(array $fullNames): array
    {
        $uniqueNames = [];
        $seenNames = [];

        foreach ($fullNames as $fullName) {
            if ($fullName === '' || isset($seenNames[$fullName])) {
                continue;
            }

            $seenNames[$fullName] = true;
            $uniqueNames[] = $fullName;
        }

        return $uniqueNames;
    }

    /**
     * Inflect the given names, mapping every source name to its inflected form.
     *
     * @param  list<string>  $uniqueNames
     * @return array<string, string>
     */
    private function requestInflectedNames(string $instruction, array $uniqueNames): array
    {
        $response = $this->agent->prompt($instruction.PHP_EOL.PHP_EOL.$this->numberedList($uniqueNames));

        $entries = $response instanceof StructuredAgentResponse
            ? data_get($response->toArray(), 'names')
            : null;

        if (! is_array($entries)) {
            throw new AwardeeNameInflectionException(
                __('Unable to inflect the awardee names: the agent returned no names.')
            );
        }

        $inflectedNames = [];

        foreach ($entries as $entry) {
            $index = data_get($entry, 'index');
            $fullName = data_get($entry, 'full_name');

            if (! is_numeric($index) || ! is_string($fullName)) {
                throw new AwardeeNameInflectionException(
                    __('Unable to inflect the awardee names: the agent returned an unexpected entry.')
                );
            }

            $sourceName = $uniqueNames[(int) $index - 1] ?? null;

            if ($sourceName === null) {
                throw new AwardeeNameInflectionException(
                    __('Unable to inflect the awardee names: the agent returned an unknown entry [:index].', ['index' => $index])
                );
            }

            $inflectedName = $this->normalizeFullName($fullName);

            if ($inflectedName === '') {
                throw new AwardeeNameInflectionException(
                    __('Unable to inflect the awardee name [:name].', ['name' => $sourceName])
                );
            }

            $inflectedNames[$sourceName] = $inflectedName;
        }

        if (count($inflectedNames) !== count($uniqueNames)) {
            throw new AwardeeNameInflectionException(
                __('Unable to inflect the awardee names: :returned of :expected names were returned.', [
                    'returned' => count($inflectedNames),
                    'expected' => count($uniqueNames),
                ])
            );
        }

        return $inflectedNames;
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
