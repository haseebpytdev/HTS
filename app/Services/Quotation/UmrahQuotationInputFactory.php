<?php

namespace App\Services\Quotation;

use App\Data\Calculator\PricingComponentInput;
use App\Data\Calculator\UmrahHotelStayInput;
use App\Data\Calculator\UmrahQuotationInput;
use App\Models\FlightEntry;
use App\Models\HotelRate;
use App\Models\TransportRate;
use App\Models\VisaRate;

/**
 * Maps validated quotation payload and loaded rate rows into calculator input.
 */
class UmrahQuotationInputFactory
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function fromValidated(
        array $validated,
        HotelRate $makkahRate,
        HotelRate $madinahRate,
        VisaRate $visaRate,
        TransportRate $transportRate,
        FlightEntry $flightEntry,
    ): UmrahQuotationInput {
        return new UmrahQuotationInput(
            adults: (int) $validated['adults'],
            children: (int) ($validated['children'] ?? 0),
            makkahHotel: new UmrahHotelStayInput(
                city: 'Makkah',
                nights: (int) $validated['makkah_nights'],
                roomRatePerNight: (float) ($validated['makkah_room_rate_override'] ?? $makkahRate->rate_per_night),
                roomBasis: $validated['makkah_room_basis'],
                roomsCount: isset($validated['makkah_rooms_count']) ? (int) $validated['makkah_rooms_count'] : null
            ),
            madinahHotel: new UmrahHotelStayInput(
                city: 'Madinah',
                nights: (int) $validated['madinah_nights'],
                roomRatePerNight: (float) ($validated['madinah_room_rate_override'] ?? $madinahRate->rate_per_night),
                roomBasis: $validated['madinah_room_basis'],
                roomsCount: isset($validated['madinah_rooms_count']) ? (int) $validated['madinah_rooms_count'] : null
            ),
            visaCost: new PricingComponentInput(
                name: 'Visa',
                amount: (float) ($validated['visa_amount_override'] ?? $visaRate->amount),
                mode: $validated['visa_pricing_mode']
            ),
            transportCost: new PricingComponentInput(
                name: 'Transport',
                amount: (float) ($validated['transport_amount_override'] ?? $transportRate->amount),
                mode: $validated['transport_pricing_mode']
            ),
            flightCost: new PricingComponentInput(
                name: 'Flight',
                amount: (float) ($validated['flight_amount_override'] ?? $flightEntry->price),
                mode: $validated['flight_pricing_mode']
            ),
            extras: [
                new PricingComponentInput(
                    name: (string) ($validated['extras_label'] ?? 'Extras'),
                    amount: (float) ($validated['extras_amount'] ?? 0),
                    mode: (string) ($validated['extras_mode'] ?? 'fixed')
                ),
            ],
            markupType: $validated['markup_type'],
            markupValue: (float) $validated['markup_value'],
            childOccupancyFactor: (float) ($validated['child_occupancy_factor'] ?? 1.0),
        );
    }
}
