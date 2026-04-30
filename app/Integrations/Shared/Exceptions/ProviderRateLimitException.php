<?php

namespace App\Integrations\Shared\Exceptions;

use RuntimeException;
use Throwable;

final class ProviderRateLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
