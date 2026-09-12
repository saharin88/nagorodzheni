<?php

namespace App\Contracts\Contracts;

use App\Services\AiAwardeeNameInflector;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(AiAwardeeNameInflector::class)]
#[Singleton]
interface AwardeeNameInflector
{
    public function toGenitive(string $fullName): string;

    public function fromGenitive(string $genitiveFullName): string;

    /**
     * Inflect many names in a single request.
     *
     * @param  list<string>  $fullNames
     * @return list<string>
     */
    public function toGenitiveMany(array $fullNames): array;

    /**
     * Restore many names in a single request.
     *
     * @param  list<string>  $genitiveFullNames
     * @return list<string>
     */
    public function fromGenitiveMany(array $genitiveFullNames): array;
}
