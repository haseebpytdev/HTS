<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleConfigurationRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'environment' => ['required', Rule::in(['development', 'sandbox', 'production'])],
            'is_default_provider' => ['sometimes', 'boolean'],
            'provider_priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'allow_fallback' => ['sometimes', 'boolean'],
            'allow_multi_provider' => ['sometimes', 'boolean'],
            'available_operations' => ['nullable', 'array'],
            'available_operations.*' => [Rule::in(['search', 'pricing', 'booking'])],

            'tax_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'tax_value' => ['required', 'numeric', 'min:0', 'max:1000000'],

            'b2b_markup_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'b2b_markup_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'b2c_markup_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'b2c_markup_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'base_currency' => ['required', 'string', 'size:3'],
            'min_markup_guard' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_discount_guard' => ['nullable', 'numeric', 'min:0', 'max:1000000'],

            'documentation_url' => ['nullable', 'url', 'max:1000'],
            'setup_guide_url' => ['nullable', 'url', 'max:1000'],
            'environment_notes' => ['nullable', 'string', 'max:3000'],
            'troubleshooting_hints' => ['nullable', 'string', 'max:3000'],

            'credentials.sandbox' => ['nullable', 'array'],
            'credentials.production' => ['nullable', 'array'],
            'credentials.sandbox.*' => ['nullable', 'string', 'max:2048'],
            'credentials.production.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
