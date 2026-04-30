<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantModuleAccessRequest extends FormRequest
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
            'rows.*.module_code' => ['required', 'string', 'max:120'],
            'rows.*.is_enabled' => ['sometimes', 'boolean'],
            'rows.*.allowed_operations' => ['nullable', 'array'],
            'rows.*.allowed_operations.*' => [Rule::in(['search', 'pricing', 'booking'])],
            'rows.*.bookings_monthly_quota' => ['nullable', 'integer', 'min:0'],
            'rows.*.searches_daily_quota' => ['nullable', 'integer', 'min:0'],
            'rows.*.soft_limit_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rows.*.hard_limit_enforced' => ['sometimes', 'boolean'],
            'rows.*.overage_alert_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
