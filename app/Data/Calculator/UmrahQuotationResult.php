<?php

namespace App\Data\Calculator;

class UmrahQuotationResult
{
    /**
     * @param array<string, float> $componentBreakdown
     */
    public function __construct(
        public readonly float $makkahHotelTotal,
        public readonly float $madinahHotelTotal,
        public readonly float $visaTotal,
        public readonly float $transportTotal,
        public readonly float $flightTotal,
        public readonly float $extrasTotal,
        public readonly float $subTotal,
        public readonly float $markupAmount,
        public readonly float $grandTotal,
        public readonly float $perPersonTotal,
        public readonly array $componentBreakdown = []
    ) {
    }
}
