<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DecreeParseException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to parse decree metadata.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
