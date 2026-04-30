<?php

namespace App\Http\Requests\Admin;

use App\Models\Traveler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AmendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amend_reason' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:8000'],
            'remarks' => ['nullable', 'string', 'max:8000'],
            'travelers' => ['nullable', 'array'],
            'travelers.*.first_name' => ['required_with:travelers', 'string', 'max:120'],
            'travelers.*.last_name' => ['required_with:travelers', 'string', 'max:120'],
            'travelers.*.date_of_birth' => ['nullable', 'date'],
            'travelers.*.passport_no' => ['nullable', 'string', 'max:64'],
            'travelers.*.nationality' => ['nullable', 'string', 'max:80'],
            'travelers.*.traveler_type' => ['nullable', 'string', Rule::in([
                Traveler::TYPE_ADULT,
                Traveler::TYPE_CHILD,
                Traveler::TYPE_INFANT,
            ])],
        ];
    }
}
