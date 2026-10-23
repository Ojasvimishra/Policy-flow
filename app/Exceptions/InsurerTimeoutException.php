<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class InsurerTimeoutException extends RuntimeException
{
    public function __construct(
        string $message = 'Connection to insurer timed out. Provider outcome is uncertain.',
        int $code = 504,
        ?Throwable $previous = null,
        public readonly ?string $clientRequestId = null,
        public readonly ?string $providerReference = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
