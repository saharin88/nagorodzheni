<?php

namespace App\Contracts;

use App\Services\CachedPresidentDecreeFetcher;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(CachedPresidentDecreeFetcher::class)]
#[Singleton]
interface DecreeHtmlFetcher
{
    public function fetchHtml(string $decreeUrl): string;
}
