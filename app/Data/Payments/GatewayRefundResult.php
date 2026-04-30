<?php

namespace App\Data\Payments;

final readonly class GatewayRefundResult
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $externalRefundId = null,
        public ?string $message = null,
        public ?array $raw = null,
    ) {}
}
