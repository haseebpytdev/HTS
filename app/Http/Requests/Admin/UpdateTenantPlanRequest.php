<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantPlanRequest extends FormRequest
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
            'plan_tier' => ['required', Rule::in(['basic', 'growth', 'pro', 'enterprise'])],
            'bookings_monthly_quota' => ['required', 'integer', 'min:0'],
            'searches_daily_quota' => ['required', 'integer', 'min:0'],
            'soft_limit_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'hard_limit_enforced' => ['sometimes', 'boolean'],
            'overage_alert_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
