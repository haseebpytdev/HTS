<?php

namespace App\Data\Calculator;

class UmrahQuotationInput
{
    /**
     * @param array<int, PricingComponentInput> $extras
     */
    public function __construct(
        public readonly int $adults,
        public readonly int $children,
        public readonly UmrahHotelStayInput $makkahHotel,
        public readonly UmrahHotelStayInput $madinahHotel,
        public readonly PricingComponentInput $visaCost,
        public readonly PricingComponentInput $transportCost,
        public readonly PricingComponentInput $flightCost,
        public readonly array $extras = [],
        public readonly string $markupType = 'fixed',
        public readonly float $markupValue = 0,
        public readonly float $childOccupancyFactor = 1.0
    ) {
    }

    public function totalPassengers(): int
    {
        return $this->adults + $this->children;
    }
}
