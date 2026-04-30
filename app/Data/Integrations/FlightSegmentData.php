<?php

namespace App\Data\Integrations;

use JsonSerializable;

/**
 * One flight segment in normalized form (post-mapper).
 */
final readonly class FlightSegmentData implements JsonSerializable
{
    public function __construct(
        public string $departureAirport,
        public string $arrivalAirport,
        public string $departureAt,
        public string $arrivalAt,
        public ?string $marketingCarrier = null,
        public ?string $operatingCarrier = null,
        public ?string $flightNumber = null,
        public ?string $cabinClass = null,
        public ?string $marketingCarrierName = null,
        public ?string $baggageSummary = null,
        public ?string $mealNote = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $row = [
            'departure_airport' => $this->departureAirport,
            'arrival_airport' => $this->arrivalAirport,
            'departure_at' => $this->departureAt,
            'arrival_at' => $this->arrivalAt,
            'marketing_carrier' => $this->marketingCarrier,
            'operating_carrier' => $this->operatingCarrier,
            'flight_number' => $this->flightNumber,
            'cabin_class' => $this->cabinClass,
        ];

        if ($this->marketingCarrierName !== null && $this->marketingCarrierName !== '') {
            $row['marketing_carrier_name'] = $this->marketingCarrierName;
        }
        if ($this->baggageSummary !== null && $this->baggageSummary !== '') {
            $row['baggage_summary'] = $this->baggageSummary;
        }
        if ($this->mealNote !== null && $this->mealNote !== '') {
            $row['meal_note'] = $this->mealNote;
        }

        return $row;
    }
}
