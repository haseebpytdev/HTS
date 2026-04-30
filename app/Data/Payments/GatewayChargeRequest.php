<?php

namespace App\Data\Payments;

use App\Models\Booking;

final readonly class GatewayChargeRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $amount,
        public string $currency,
        public ?Booking $booking = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {}
}
