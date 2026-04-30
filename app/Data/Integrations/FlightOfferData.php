<?php

namespace App\Data\Integrations;

use JsonSerializable;

/**
 * A shoppable offer: segments + optional pricing (normalized).
 *
 * @phpstan-type SegmentList list<FlightSegmentData>
 */
final readonly class FlightOfferData implements JsonSerializable
{
    /**
     * @param  list<FlightSegmentData>  $segments
     */
    public function __construct(
        public string $id,
        public string $providerOfferReference,
        public array $segments,
        public ?PriceBreakdownData $price = null,
        public ?string $cabinSummary = null,
        public ?NormalizedPayloadMetadata $metadata = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $base = [
            'id' => $this->id,
            'provider_offer_reference' => $this->providerOfferReference,
            'segments' => array_map(
                static fn (FlightSegmentData $s): array => $s->jsonSerialize(),
                $this->segments
            ),
            'cabin_summary' => $this->cabinSummary,
            'price' => $this->price?->jsonSerialize(),
        ];

        if ($this->metadata !== null) {
            return array_merge($this->metadata->jsonSerialize(), $base);
        }

        return $base;
    }
}
