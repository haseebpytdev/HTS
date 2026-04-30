<?php

namespace App\Integrations\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Failed to map supplier payload into normalized DTOs.
 */
final class ProviderMappingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
