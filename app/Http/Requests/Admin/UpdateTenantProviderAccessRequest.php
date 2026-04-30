<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantProviderAccessRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.provider' => ['required', Rule::in(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'iati', 'duffel'])],
            'rows.*.environment' => ['nullable', Rule::in(['sandbox', 'production', 'test', 'testing', 'development', 'live'])],
            'rows.*.plan_code' => ['nullable', 'string', 'max:64'],
            'rows.*.can_search' => ['sometimes', 'boolean'],
            'rows.*.can_price' => ['sometimes', 'boolean'],
            'rows.*.can_book' => ['sometimes', 'boolean'],
            'rows.*.allow_multi_provider' => ['sometimes', 'boolean'],
            'rows.*.allow_fallback' => ['sometimes', 'boolean'],
            'rows.*.priority_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'rows.*.is_enabled' => ['sometimes', 'boolean'],
            'rows.*.bookings_monthly_quota' => ['nullable', 'integer', 'min:0'],
            'rows.*.searches_daily_quota' => ['nullable', 'integer', 'min:0'],
            'rows.*.soft_limit_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rows.*.hard_limit_enforced' => ['sometimes', 'boolean'],
            'rows.*.overage_alert_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
