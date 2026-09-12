<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AwardeeNameInflectionException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to inflect the awardee name.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
