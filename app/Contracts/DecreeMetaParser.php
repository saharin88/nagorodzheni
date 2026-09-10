<?php

namespace App\Contracts;

use App\Services\PresidentDecreeMetaParser;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(PresidentDecreeMetaParser::class)]
#[Singleton]
interface DecreeMetaParser
{
    public function getDecreeNumber(string $decreeUrl): string;

    public function getDecreeDate(string $decreeUrl): CarbonImmutable;
}
