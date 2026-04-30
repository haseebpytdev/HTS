<?php

namespace App\Data\Integrations;

use JsonSerializable;

/**
 * Normalized supplier error for logging / API responses — map vendor payloads here only.
 */
final readonly class ApiErrorData implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $message,
        public string $supplierCode,
        public ?string $correlationId = null,
        public ?int $httpStatus = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'supplier_code' => $this->supplierCode,
            'correlation_id' => $this->correlationId,
            'http_status' => $this->httpStatus,
        ];
    }
}
