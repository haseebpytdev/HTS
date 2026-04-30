<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\FlightEntry;
use App\Models\HotelRate;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuotationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('code', 'APS-LHR')->first();
        $salesUser = User::where('email', 'sales@apnasafar.test')->first();
        $inquiry = Inquiry::where('email', 'customer@example.com')->first();
        $hotelRate = HotelRate::first();
        $flight = FlightEntry::first();

        $quotation = Quotation::updateOrCreate(
            ['quote_number' => 'QTN-2026-0001'],
            [
                'agency_id' => $agency?->id,
                'user_id' => $salesUser?->id,
                'inquiry_id' => $inquiry?->id,
                'customer_name' => $inquiry?->name ?? 'Ahmed Khan',
                'customer_email' => $inquiry?->email ?? 'customer@example.com',
                'customer_phone' => $inquiry?->phone ?? '+92-300-0000000',
                'travel_date' => now()->addMonths(2)->toDateString(),
                'return_date' => now()->addMonths(2)->addDays(10)->toDateString(),
                'adults' => 2,
                'children' => 1,
                'currency' => 'PKR',
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 10000,
                'total_amount' => 0,
                'status' => 'sent',
                'notes' => 'Demo quotation for local testing.',
                'expires_at' => now()->addDays(7),
            ]
        );

        if ($flight) {
            QuotationItem::updateOrCreate(
                ['quotation_id' => $quotation->id, 'item_type' => 'flight', 'title' => 'LHE to JED Flight'],
                [
                    'reference_type' => FlightEntry::class,
                    'reference_id' => $flight->id,
                    'description' => 'Saudi Airlines economy class',
                    'quantity' => 3,
                    'unit_price' => 135000,
                    'total_price' => 405000,
                    'meta' => ['flight_no' => $flight->flight_no],
                ]
            );
        }

        if ($hotelRate) {
            QuotationItem::updateOrCreate(
                ['quotation_id' => $quotation->id, 'item_type' => 'hotel', 'title' => 'Makkah Hotel Stay'],
                [
                    'reference_type' => HotelRate::class,
                    'reference_id' => $hotelRate->id,
                    'description' => '10 nights',
                    'quantity' => 10,
                    'unit_price' => $hotelRate->rate_per_night,
                    'total_price' => $hotelRate->rate_per_night * 10,
                    'meta' => ['meal_plan' => $hotelRate->meal_plan],
                ]
            );
        }

        $subtotal = (float) $quotation->items()->sum('total_price');
        $taxAmount = round($subtotal * 0.05, 2);
        $discount = (float) $quotation->discount_amount;
        $total = max(0, $subtotal + $taxAmount - $discount);

        $quotation->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
        ]);

        Booking::updateOrCreate(
            ['booking_number' => 'BKG-2026-0001'],
            [
                'quotation_id' => $quotation->id,
                'agency_id' => $agency?->id,
                'user_id' => $salesUser?->id,
                'status' => 'confirmed',
                'booked_at' => now(),
                'total_amount' => $quotation->total_amount,
                'currency' => 'PKR',
                'payment_status' => 'partial',
                'remarks' => 'Demo booking from quotation.',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'site_name'],
            ['value' => 'Hayat Travel Solutions', 'type' => 'string', 'group' => 'general', 'is_public' => true]
        );
        Setting::updateOrCreate(
            ['key' => 'support_email'],
            ['value' => 'support@apnasafar.test', 'type' => 'string', 'group' => 'general', 'is_public' => true]
        );
        Setting::updateOrCreate(
            ['key' => 'default_currency'],
            ['value' => 'PKR', 'type' => 'string', 'group' => 'pricing', 'is_public' => false]
        );
    }
}
