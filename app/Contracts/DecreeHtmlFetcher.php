<?php

namespace App\Contracts;

use App\Services\PresidentDecreeHtmlFetcher;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(PresidentDecreeHtmlFetcher::class)]
#[Singleton]
interface DecreeHtmlFetcher
{
    /**
     * Get the page, serving it from the disk cache when it was downloaded before.
     */
    public function fetchHtml(string $url): string;

    /**
     * Get the page from the site, bypassing the disk cache.
     *
     * Pages whose content changes between the requests, such as the decree list,
     * must be re-read every time, so they are never served from the cache.
     */
    public function fetchFreshHtml(string $url): string;
}
