<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlightPricingRequest extends FormRequest
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
            'offer_reference' => ['required', 'string', 'max:256'],
            'opaque_context' => ['nullable', 'array'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'provider' => ['nullable', 'string', Rule::in(config('integrations.supported_drivers', []))],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['string', Rule::in(config('integrations.supported_drivers', []))],
            'allow_fallback' => ['sometimes', 'boolean'],
        ];
    }
}
