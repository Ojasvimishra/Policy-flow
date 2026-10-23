<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class InsurerTemporaryFailureException extends RuntimeException
{
    public function __construct(
        string $message = 'Insurer service temporarily unavailable. Request may be safely retried.',
        int $code = 503,
        ?Throwable $previous = null,
        public readonly bool $retriable = true
    ) {
        parent::__construct($message, $code, $previous);
    }
}
