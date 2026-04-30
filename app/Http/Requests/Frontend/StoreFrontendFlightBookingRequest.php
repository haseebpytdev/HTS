<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFrontendFlightBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'travelers' => ['required', 'array', 'min:1'],
            'travelers.*.traveler_type' => ['required', 'string', Rule::in(['adult', 'child', 'infant'])],
            'travelers.*.given_name' => ['required', 'string', 'max:120'],
            'travelers.*.family_name' => ['required', 'string', 'max:120'],
            'travelers.*.date_of_birth' => ['nullable', 'date'],
            'travelers.*.nationality' => ['nullable', 'string', 'size:2'],
        ];
    }
}
