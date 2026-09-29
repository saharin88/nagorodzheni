<?php

namespace App\Contracts;

use App\Services\AiAwardeeNameInflector;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(AiAwardeeNameInflector::class)]
#[Singleton]
interface AwardeeNameInflector
{
    public function fromGenitive(string $genitiveFullName): string;

    /**
     * Restore the nominative form of many awardee names in a single request.
     *
     * @param  list<string>  $genitiveFullNames
     * @return array<string, string>
     */
    public function fromGenitiveMany(array $genitiveFullNames): array;
}
