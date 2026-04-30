<?php

namespace App\Integrations\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Network / timeout / transport failure talking to the supplier.
 */
final class ProviderTransportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
