<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlightEntryRequest extends FormRequest
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
            'airline' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'size:3'],
            'destination' => ['required', 'string', 'size:3'],
            'depart_at' => ['nullable', 'date'],
            'arrive_at' => ['nullable', 'date', 'after_or_equal:depart_at'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'flight_no' => ['nullable', 'string', 'max:255'],
            'cabin_class' => ['nullable', Rule::in(['economy', 'premium_economy', 'business', 'first'])],
            'seats_available' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'PKR')),
            'origin' => strtoupper((string) $this->input('origin')),
            'destination' => strtoupper((string) $this->input('destination')),
        ]);
    }
}
