<?php

namespace App\Services\Calculator;

use App\Data\Calculator\PricingComponentInput;
use App\Data\Calculator\UmrahQuotationInput;
use App\Data\Calculator\UmrahQuotationResult;
use App\Services\Pricing\ComponentPricingService;
use App\Services\Pricing\RoomPricingService;

class UmrahQuotationCalculator
{
    public function __construct(
        private readonly RoomPricingService $roomPricingService,
        private readonly ComponentPricingService $componentPricingService
    ) {
    }

    public function calculate(UmrahQuotationInput $input): UmrahQuotationResult
    {
        $passengers = max($input->totalPassengers(), 1);

        $makkahHotelTotal = $this->roomPricingService->calculateTotal(
            $input->makkahHotel,
            $input->adults,
            $input->children,
            $input->childOccupancyFactor
        );

        $madinahHotelTotal = $this->roomPricingService->calculateTotal(
            $input->madinahHotel,
            $input->adults,
            $input->children,
            $input->childOccupancyFactor
        );

        $visaTotal = $this->componentPricingService->calculate($input->visaCost, $passengers);
        $transportTotal = $this->componentPricingService->calculate($input->transportCost, $passengers);
        $flightTotal = $this->componentPricingService->calculate($input->flightCost, $passengers);
        $extrasTotal = $this->extrasTotal($input->extras, $passengers);

        $subTotal = round(
            $makkahHotelTotal + $madinahHotelTotal + $visaTotal + $transportTotal + $flightTotal + $extrasTotal,
            2
        );

        $markupAmount = $this->markupAmount($subTotal, $input->markupType, $input->markupValue);
        $grandTotal = round($subTotal + $markupAmount, 2);
        $perPersonTotal = round($grandTotal / $passengers, 2);

        return new UmrahQuotationResult(
            makkahHotelTotal: $makkahHotelTotal,
            madinahHotelTotal: $madinahHotelTotal,
            visaTotal: $visaTotal,
            transportTotal: $transportTotal,
            flightTotal: $flightTotal,
            extrasTotal: $extrasTotal,
            subTotal: $subTotal,
            markupAmount: $markupAmount,
            grandTotal: $grandTotal,
            perPersonTotal: $perPersonTotal,
            componentBreakdown: [
                'makkah_hotel' => $makkahHotelTotal,
                'madinah_hotel' => $madinahHotelTotal,
                'visa' => $visaTotal,
                'transport' => $transportTotal,
                'flight' => $flightTotal,
                'extras' => $extrasTotal,
                'subtotal' => $subTotal,
                'markup' => $markupAmount,
                'grand_total' => $grandTotal,
                'per_person' => $perPersonTotal,
            ]
        );
    }

    /**
     * @param array<int, PricingComponentInput> $extras
     */
    private function extrasTotal(array $extras, int $passengers): float
    {
        $total = 0.0;

        foreach ($extras as $extra) {
            $total += $this->componentPricingService->calculate($extra, $passengers);
        }

        return round($total, 2);
    }

    private function markupAmount(float $subTotal, string $markupType, float $markupValue): float
    {
        if ($markupType === 'percentage') {
            return round(($subTotal * $markupValue) / 100, 2);
        }

        return round($markupValue, 2);
    }
}
