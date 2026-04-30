<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlightSearchRequest extends FormRequest
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
            'origin' => ['required', 'string', 'size:3'],
            'destination' => ['required', 'string', 'size:3'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'infants' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'cabin_class' => ['nullable', 'string', 'max:32'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'provider' => ['nullable', 'string', Rule::in(config('integrations.supported_drivers', []))],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['string', Rule::in(config('integrations.supported_drivers', []))],
            'multi_provider' => ['sometimes', 'boolean'],
            'allow_fallback' => ['sometimes', 'boolean'],
        ];
    }
}
