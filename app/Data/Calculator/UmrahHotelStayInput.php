<?php

namespace App\Data\Calculator;

class UmrahHotelStayInput
{
    public function __construct(
        public readonly string $city,
        public readonly int $nights,
        public readonly float $roomRatePerNight,
        public readonly string $roomBasis = 'quad',
        public readonly ?int $roomsCount = null
    ) {
    }
}
