<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantIntegrationPolicyRequest extends FormRequest
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
            'allowed_providers' => ['array'],
            'allowed_providers.*' => ['string', Rule::in(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'iati', 'duffel'])],
            'search_enabled' => ['sometimes', 'boolean'],
            'pricing_enabled' => ['sometimes', 'boolean'],
            'booking_enabled' => ['sometimes', 'boolean'],
            'allow_multi_provider' => ['sometimes', 'boolean'],
            'allow_fallback' => ['sometimes', 'boolean'],
            'provider_priority' => ['nullable', 'string', 'max:255'],
        ];
    }
}
