<?php

namespace App\Data\Integrations;

use JsonSerializable;

/**
 * Booking snapshot after normalization (create / retrieve / cancel).
 *
 * @phpstan-type TravelerList list<TravelerData>
 */
final readonly class BookingData implements JsonSerializable
{
    /**
     * @param  list<TravelerData>  $travelers
     */
    public function __construct(
        public string $status,
        public string $providerCode,
        public ?string $bookingReference = null,
        public ?string $pnr = null,
        public array $travelers = [],
        public ?PriceBreakdownData $totalPrice = null,
        public ?string $createdAt = null,
        public ?NormalizedPayloadMetadata $metadata = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $base = [
            'provider' => $this->providerCode,
            'status' => $this->status,
            'booking_reference' => $this->bookingReference,
            'pnr' => $this->pnr,
            'travelers' => array_map(
                static fn (TravelerData $t): array => $t->jsonSerialize(),
                $this->travelers
            ),
            'total_price' => $this->totalPrice?->jsonSerialize(),
            'created_at' => $this->createdAt,
        ];

        if ($this->metadata !== null) {
            return array_merge($this->metadata->jsonSerialize(), $base);
        }

        return $base;
    }
}
