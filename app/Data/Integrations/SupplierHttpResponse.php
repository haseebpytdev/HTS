<?php

namespace App\Data\Integrations;

/**
 * Result of a supplier HTTP call after JSON decoding (raw body retained for logging).
 *
 * @phpstan-type HeaderMap array<string, string>
 */
final readonly class SupplierHttpResponse
{
    /**
     * @param  array<string, mixed>  $decodedJson
     * @param  array<string, string>  $headers  Single-value header map (first line per name).
     */
    public function __construct(
        public int $statusCode,
        public string $rawBody,
        public array $decodedJson,
        public array $headers = [],
    ) {
    }

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
