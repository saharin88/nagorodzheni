<?php

namespace App\Contracts;

use App\Services\PresidentDecreeParser;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(PresidentDecreeParser::class)]
#[Singleton]
interface DecreeMetaParser
{
    public function getDecreeNumber(string $decreeUrl): string;

    public function getDecreeDate(string $decreeUrl): CarbonImmutable;
}
