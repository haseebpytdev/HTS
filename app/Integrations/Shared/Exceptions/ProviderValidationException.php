<?php

namespace App\Integrations\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Supplier rejected the request payload (4xx validation / business rule).
 */
final class ProviderValidationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $supplierContext
     */
    public function __construct(
        string $message,
        public readonly string $providerCode,
        public readonly array $supplierContext = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
