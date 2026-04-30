<?php

namespace App\Data\Integrations;

/**
 * Internal flight shopping request — never pass raw supplier payloads here.
 */
final readonly class FlightSearchRequestData
{
    public function __construct(
        public string $origin,
        public string $destination,
        public string $departureDate,
        public int $adults = 1,
        public int $children = 0,
        public int $infants = 0,
        public ?string $cabinClass = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshotArray(): array
    {
        return array_filter([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure_date' => $this->departureDate,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'cabin_class' => $this->cabinClass,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }
}
