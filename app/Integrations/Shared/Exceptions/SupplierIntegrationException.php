<?php

namespace App\Integrations\Shared\Exceptions;

use App\Data\Integrations\ApiErrorData;
use RuntimeException;
use Throwable;

/**
 * Normalized failure from any supplier adapter. Map vendor errors here; never leak raw vendor payloads to UI unchecked.
 */
final class SupplierIntegrationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $supplierContext
     */
    public function __construct(
        string $message,
        public readonly string $supplierCode,
        public readonly string $normalizedCode,
        public readonly array $supplierContext = [],
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?ApiErrorData $apiError = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
