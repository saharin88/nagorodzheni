<?php

namespace App\Contracts;

use App\Exceptions\DecreeParseException;
use App\Services\AwardDecreeSynchronizer as AwardDecreeSynchronizerService;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

#[Bind(AwardDecreeSynchronizerService::class)]
#[Singleton]
interface AwardDecreeSynchronizer
{
    /**
     * Store the decrees about state awards that the President's site published since the last run,
     * together with the awardees they mention.
     *
     * @return array{added: int, awardees: int, skipped: int}
     *
     * @throws DecreeParseException when the decree list cannot be read
     */
    public function sync(): array;
}
