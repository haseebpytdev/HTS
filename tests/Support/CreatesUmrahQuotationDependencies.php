<?php

namespace Tests\Support;

use App\Models\Agency;
use App\Models\FlightEntry;
use App\Models\Hotel;
use App\Models\HotelRate;
use App\Models\HotelRoomType;
use App\Models\TransportRate;
use App\Models\TransportType;
use App\Models\VisaRate;
use App\Models\VisaType;

trait CreatesUmrahQuotationDependencies
{
    /**
     * Minimal B2B master data so StoreQuotationRequest validation and UpsertQuotationAction succeed.
     *
     * @return array{agency: Agency, makkah_hotel_rate_id: int, madinah_hotel_rate_id: int, visa_rate_id: int, transport_rate_id: int, flight_entry_id: int}
     */
    protected function createUmrahQuotationDependencies(): array
    {
        $agency = Agency::query()->create([
            'name' => 'Test Agency',
            'code' => 'TA-'.substr(str_replace('.', '', uniqid('', true)), 0, 10),
            'is_active' => true,
        ]);

        $hotelMakkah = Hotel::query()->create([
            'name' => 'Makkah Hotel Test',
            'slug' => 'makkah-hotel-test-'.uniqid(),
            'city' => 'Makkah',
            'is_active' => true,
        ]);
        $roomMakkah = HotelRoomType::query()->create([
            'hotel_id' => $hotelMakkah->id,
            'name' => 'Double',
            'is_active' => true,
        ]);
        $makkahRate = HotelRate::query()->create([
            'hotel_room_type_id' => $roomMakkah->id,
            'currency' => 'PKR',
            'rate_per_night' => 25000,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => null,
            'is_active' => true,
        ]);

        $hotelMadinah = Hotel::query()->create([
            'name' => 'Madinah Hotel Test',
            'slug' => 'madinah-hotel-test-'.uniqid(),
            'city' => 'Madinah',
            'is_active' => true,
        ]);
        $roomMadinah = HotelRoomType::query()->create([
            'hotel_id' => $hotelMadinah->id,
            'name' => 'Double',
            'is_active' => true,
        ]);
        $madinahRate = HotelRate::query()->create([
            'hotel_room_type_id' => $roomMadinah->id,
            'currency' => 'PKR',
            'rate_per_night' => 20000,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => null,
            'is_active' => true,
        ]);

        $visaType = VisaType::query()->create([
            'name' => 'Umrah Visa Test',
            'slug' => 'umrah-visa-test-'.uniqid(),
            'is_active' => true,
        ]);
        $visaRate = VisaRate::query()->create([
            'visa_type_id' => $visaType->id,
            'currency' => 'PKR',
            'amount' => 40000,
            'valid_from' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $transportType = TransportType::query()->create([
            'name' => 'Bus Test',
            'slug' => 'bus-test-'.uniqid(),
            'is_active' => true,
        ]);
        $transportRate = TransportRate::query()->create([
            'transport_type_id' => $transportType->id,
            'currency' => 'PKR',
            'amount' => 15000,
            'valid_from' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $flight = FlightEntry::query()->create([
            'origin' => 'KHI',
            'destination' => 'JED',
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        return [
            'agency' => $agency,
            'makkah_hotel_rate_id' => $makkahRate->id,
            'madinah_hotel_rate_id' => $madinahRate->id,
            'visa_rate_id' => $visaRate->id,
            'transport_rate_id' => $transportRate->id,
            'flight_entry_id' => $flight->id,
        ];
    }
}
