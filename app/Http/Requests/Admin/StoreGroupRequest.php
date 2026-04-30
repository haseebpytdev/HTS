<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
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
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'name' => ['required', 'string', 'max:255'],
            'departure_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'seats_left' => ['required', 'integer', 'min:0', 'max:100000'],
            'pricing_tiers' => ['nullable', 'string'],
            'airline_info' => ['nullable', 'string'],
            'hotel_info' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
