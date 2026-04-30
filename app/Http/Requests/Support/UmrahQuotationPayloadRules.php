<?php

namespace App\Http\Requests\Support;

use Illuminate\Validation\Rule;

final class UmrahQuotationPayloadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'agency_id' => ['required', 'exists:agencies,id'],
            'inquiry_id' => ['nullable', 'exists:inquiries,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'travel_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:travel_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:20'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'in:draft,sent,approved,rejected'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],

            'makkah_hotel_rate_id' => ['required', 'exists:hotel_rates,id'],
            'makkah_nights' => ['required', 'integer', 'min:1', 'max:90'],
            'makkah_room_basis' => ['required', 'in:single,double,triple,quad'],
            'makkah_room_rate_override' => ['nullable', 'numeric', 'min:0'],
            'makkah_rooms_count' => ['nullable', 'integer', 'min:1', 'max:50'],

            'madinah_hotel_rate_id' => ['required', 'exists:hotel_rates,id'],
            'madinah_nights' => ['required', 'integer', 'min:1', 'max:90'],
            'madinah_room_basis' => ['required', 'in:single,double,triple,quad'],
            'madinah_room_rate_override' => ['nullable', 'numeric', 'min:0'],
            'madinah_rooms_count' => ['nullable', 'integer', 'min:1', 'max:50'],

            'visa_rate_id' => ['required', 'exists:visa_rates,id'],
            'visa_pricing_mode' => ['required', 'in:fixed,per_person'],
            'visa_amount_override' => ['nullable', 'numeric', 'min:0'],

            'transport_rate_id' => ['required', 'exists:transport_rates,id'],
            'transport_pricing_mode' => ['required', 'in:fixed,per_person'],
            'transport_amount_override' => ['nullable', 'numeric', 'min:0'],

            'flight_entry_id' => ['required', 'exists:flight_entries,id'],
            'flight_pricing_mode' => ['required', 'in:fixed,per_person'],
            'flight_amount_override' => ['nullable', 'numeric', 'min:0'],
            'integration_provider' => ['nullable', 'string', Rule::in((array) config('integrations.supported_drivers', ['duffel']))],
            'integration_offer_reference' => ['nullable', 'string', 'max:191', 'required_with:integration_provider'],
            'integration_correlation_id' => ['nullable', 'string', 'max:191'],
            'integration_selected_passengers_csv' => ['nullable', 'string', 'max:500'],

            'extras_label' => ['nullable', 'string', 'max:120'],
            'extras_amount' => ['nullable', 'numeric', 'min:0'],
            'extras_mode' => ['nullable', 'in:fixed,per_person'],

            'markup_type' => ['nullable', 'in:fixed,percentage'],
            'markup_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'promo_code' => ['nullable', 'string', 'max:80'],
            'child_occupancy_factor' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
