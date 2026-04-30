<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterIntegrationConnectionRequest extends FormRequest
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
            'provider' => ['nullable', Rule::in(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'iati', 'duffel'])],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'environment' => ['nullable', Rule::in(['sandbox', 'test', 'testing', 'development', 'production', 'live'])],
            'status' => ['nullable', Rule::in(['untested', 'healthy', 'failed', 'disabled'])],
            'active' => ['nullable', Rule::in(['0', '1'])],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
