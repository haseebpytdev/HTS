<?php

namespace App\Data\Payments;

final readonly class GatewayRefundRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $amount,
        public string $currency,
        public ?string $parentExternalId = null,
        public array $metadata = [],
    ) {}
}
