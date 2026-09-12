<?php

namespace App\Contracts\Contracts;

use App\Services\PresidentDecreeMetaParser;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(PresidentDecreeMetaParser::class)]
#[Singleton]
interface DecreeAwardeeParser
{
    /**
     * Get every awardee mentioned in the decree.
     *
     * @return list<array{full_name: string, rank: string, award: string, is_posthumous: bool}>
     */
    public function getAwardees(string $decreeUrl): array;
}
