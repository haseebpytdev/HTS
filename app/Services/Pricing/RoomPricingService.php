<?php

namespace App\Services\Pricing;

use App\Data\Calculator\UmrahHotelStayInput;

class RoomPricingService
{
    public function calculateTotal(
        UmrahHotelStayInput $stay,
        int $adults,
        int $children,
        float $childOccupancyFactor = 1.0
    ): float {
        $capacity = $this->capacityByBasis($stay->roomBasis);
        $occupancyUnits = $adults + ($children * $childOccupancyFactor);
        $computedRooms = (int) ceil(max($occupancyUnits, 1) / $capacity);
        $rooms = $stay->roomsCount ?? $computedRooms;

        return round($rooms * $stay->nights * $stay->roomRatePerNight, 2);
    }

    private function capacityByBasis(string $roomBasis): int
    {
        return match (strtolower($roomBasis)) {
            'single' => 1,
            'double' => 2,
            'triple' => 3,
            default => 4,
        };
    }
}
