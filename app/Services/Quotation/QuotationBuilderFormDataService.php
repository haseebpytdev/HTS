<?php

namespace App\Services\Quotation;

use App\Models\Agency;
use App\Models\FlightEntry;
use App\Models\HotelRate;
use App\Models\Inquiry;
use App\Models\TransportRate;
use App\Models\VisaRate;

/**
 * Loads dropdown / option data for admin quotation create/edit forms.
 */
class QuotationBuilderFormDataService
{
    /**
     * @return array{
     *     agencies: \Illuminate\Database\Eloquent\Collection<int, Agency>,
     *     inquiries: \Illuminate\Database\Eloquent\Collection<int, Inquiry>,
     *     hotelRates: \Illuminate\Database\Eloquent\Collection<int, HotelRate>,
     *     visaRates: \Illuminate\Database\Eloquent\Collection<int, VisaRate>,
     *     transportRates: \Illuminate\Database\Eloquent\Collection<int, TransportRate>,
     *     flightEntries: \Illuminate\Database\Eloquent\Collection<int, FlightEntry>
     * }
     */
    public function forBuilder(): array
    {
        return [
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
            'inquiries' => Inquiry::latest('id')->limit(100)->get(['id', 'name', 'email']),
            'hotelRates' => HotelRate::with('roomType.hotel')->where('is_active', true)->orderBy('id', 'desc')->get(),
            'visaRates' => VisaRate::with('visaType')->where('is_active', true)->orderBy('id', 'desc')->get(),
            'transportRates' => TransportRate::with('transportType')->where('is_active', true)->orderBy('id', 'desc')->get(),
            'flightEntries' => FlightEntry::where('is_active', true)->orderBy('id', 'desc')->get(),
        ];
    }
}
