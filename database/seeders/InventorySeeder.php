<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\FlightEntry;
use App\Models\Hotel;
use App\Models\HotelRate;
use App\Models\HotelRoomType;
use App\Models\TransportRate;
use App\Models\TransportType;
use App\Models\VisaRate;
use App\Models\VisaType;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $lhrAgency = Agency::where('code', 'APS-LHR')->first();
        $khiAgency = Agency::where('code', 'APS-KHI')->first();

        $makkahHotel = Hotel::updateOrCreate(
            ['slug' => 'makkah-royal-tower'],
            [
                'agency_id' => $lhrAgency?->id,
                'name' => 'Makkah Royal Tower',
                'city' => 'Makkah',
                'country' => 'Saudi Arabia',
                'star_rating' => 5,
                'address' => 'Ibrahim Al Khalil Road, Makkah',
                'description' => 'Premium hotel near Masjid Al Haram.',
                'is_active' => true,
            ]
        );

        $madinahHotel = Hotel::updateOrCreate(
            ['slug' => 'madinah-noor-residency'],
            [
                'agency_id' => $khiAgency?->id,
                'name' => 'Madinah Noor Residency',
                'city' => 'Madinah',
                'country' => 'Saudi Arabia',
                'star_rating' => 4,
                'address' => 'Central Area, Madinah',
                'description' => 'Comfort stay close to Masjid Nabawi.',
                'is_active' => true,
            ]
        );

        $makkahQuad = HotelRoomType::updateOrCreate(
            ['hotel_id' => $makkahHotel->id, 'name' => 'Quad Room'],
            ['max_adults' => 4, 'max_children' => 2, 'base_capacity' => 4, 'is_active' => true]
        );

        $madinahTriple = HotelRoomType::updateOrCreate(
            ['hotel_id' => $madinahHotel->id, 'name' => 'Triple Room'],
            ['max_adults' => 3, 'max_children' => 1, 'base_capacity' => 3, 'is_active' => true]
        );

        HotelRate::updateOrCreate(
            ['hotel_room_type_id' => $makkahQuad->id, 'agency_id' => $lhrAgency?->id, 'meal_plan' => 'Breakfast'],
            [
                'currency' => 'PKR',
                'rate_per_night' => 35000,
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
            ]
        );

        HotelRate::updateOrCreate(
            ['hotel_room_type_id' => $madinahTriple->id, 'agency_id' => $khiAgency?->id, 'meal_plan' => 'Half Board'],
            [
                'currency' => 'PKR',
                'rate_per_night' => 28000,
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
            ]
        );

        $umrahVisa = VisaType::updateOrCreate(
            ['slug' => 'umrah-30-days'],
            ['name' => 'Umrah Visa 30 Days', 'processing_days' => 5, 'is_active' => true]
        );

        $visitVisa = VisaType::updateOrCreate(
            ['slug' => 'saudi-visit-90-days'],
            ['name' => 'Saudi Visit Visa 90 Days', 'processing_days' => 10, 'is_active' => true]
        );

        VisaRate::updateOrCreate(
            ['visa_type_id' => $umrahVisa->id, 'agency_id' => $lhrAgency?->id],
            [
                'currency' => 'PKR',
                'amount' => 45000,
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addMonths(4)->toDateString(),
                'is_active' => true,
            ]
        );

        VisaRate::updateOrCreate(
            ['visa_type_id' => $visitVisa->id, 'agency_id' => $khiAgency?->id],
            [
                'currency' => 'PKR',
                'amount' => 65000,
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addMonths(4)->toDateString(),
                'is_active' => true,
            ]
        );

        $busType = TransportType::updateOrCreate(
            ['slug' => 'bus'],
            ['name' => 'Bus', 'description' => 'Intercity bus transport', 'is_active' => true]
        );
        $carType = TransportType::updateOrCreate(
            ['slug' => 'private-car'],
            ['name' => 'Private Car', 'description' => 'Private transfer service', 'is_active' => true]
        );

        TransportRate::updateOrCreate(
            ['transport_type_id' => $busType->id, 'agency_id' => $lhrAgency?->id, 'route_from' => 'Jeddah', 'route_to' => 'Makkah'],
            [
                'vehicle_name' => 'Luxury Bus',
                'trip_type' => 'one_way',
                'currency' => 'PKR',
                'amount' => 12000,
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
            ]
        );

        TransportRate::updateOrCreate(
            ['transport_type_id' => $carType->id, 'agency_id' => $khiAgency?->id, 'route_from' => 'Makkah', 'route_to' => 'Madinah'],
            [
                'vehicle_name' => 'Sedan',
                'trip_type' => 'one_way',
                'currency' => 'PKR',
                'amount' => 18000,
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
            ]
        );

        FlightEntry::updateOrCreate(
            ['origin' => 'LHE', 'destination' => 'JED', 'flight_no' => 'SV-739'],
            [
                'agency_id' => $lhrAgency?->id,
                'airline' => 'Saudi Airlines',
                'depart_at' => now()->addWeeks(1),
                'arrive_at' => now()->addWeeks(1)->addHours(5),
                'cabin_class' => 'economy',
                'seats_available' => 24,
                'currency' => 'PKR',
                'price' => 135000,
                'is_active' => true,
            ]
        );
    }
}
