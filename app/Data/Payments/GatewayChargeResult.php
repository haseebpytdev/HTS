<?php

namespace App\Data\Payments;

final readonly class GatewayChargeResult
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $externalId = null,
        public ?string $message = null,
        public ?array $raw = null,
    ) {}
}
