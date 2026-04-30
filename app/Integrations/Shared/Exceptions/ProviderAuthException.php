<?php

namespace App\Integrations\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Auth-related supplier failure (e.g. HTTP 401). Used for one-shot token refresh retry.
 */
final class ProviderAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode,
        public readonly int $httpStatus,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isUnauthorized(): bool
    {
        return $this->httpStatus === 401;
    }
}
