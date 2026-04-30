<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'travelers' => ['required', 'array', 'min:1'],
            'travelers.*.traveler_type' => ['required', 'string', 'in:adult,child,infant'],
            'travelers.*.given_name' => ['required', 'string', 'max:120'],
            'travelers.*.family_name' => ['required', 'string', 'max:120'],
            'travelers.*.date_of_birth' => ['nullable', 'date'],
            'travelers.*.nationality' => ['nullable', 'string', 'size:2'],
            'provider' => ['nullable', 'string', Rule::in(config('integrations.supported_drivers', []))],
        ];
    }
}
