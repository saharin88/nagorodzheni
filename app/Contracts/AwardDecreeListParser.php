<?php

namespace App\Contracts;

use App\Services\PresidentAwardDecreeListParser;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(PresidentAwardDecreeListParser::class)]
#[Singleton]
interface AwardDecreeListParser
{
    /**
     * Get the decrees about state awards listed on the search page of the President's site.
     *
     * The list is served newest first, so the first page carries the recent decrees.
     *
     * @return list<array{number: string, url: string}>
     */
    public function getDecrees(string $listUrl): array;
}
