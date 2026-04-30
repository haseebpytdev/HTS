<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-tenancy-settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_gateway_default' => ['required', Rule::in(['manual', 'stripe'])],
            'payment_gateway_timeout_seconds' => ['required', 'integer', 'min:5', 'max:180'],
            'payment_gateway_max_retries' => ['required', 'integer', 'min:0', 'max:5'],
            'deposit_min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'deposit_min_amount' => ['required', 'numeric', 'min:0'],
            'deposit_allow_balance_installments' => ['sometimes', 'boolean'],
            'refund_auto_approve_limit' => ['required', 'numeric', 'min:0'],
            'refund_reason_required' => ['sometimes', 'boolean'],
            'refund_max_days_since_payment' => ['required', 'integer', 'min:0', 'max:3650'],
            'wallet_credit_limit_default' => ['required', 'numeric', 'min:0'],
            'wallet_terms_days_default' => ['required', 'integer', 'min:0', 'max:365'],
            'markup_mode_default' => ['required', Rule::in(['percentage', 'fixed'])],
            'markup_value_default' => ['required', 'numeric', 'min:0'],
            'tax_percent_default' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_number_prefix' => ['required', 'string', 'max:20'],
            'invoice_sequence_padding' => ['required', 'integer', 'min:3', 'max:12'],
            'invoice_next_sequence' => ['required', 'integer', 'min:1', 'max:999999999'],
            'allow_manual_ledger_adjustments' => ['sometimes', 'boolean'],
            'overdue_threshold_days' => ['required', 'integer', 'min:0', 'max:365'],
            'revenue_report_default_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'revenue_report_include_cancelled' => ['sometimes', 'boolean'],
            'net_margin_dashboard_enabled' => ['sometimes', 'boolean'],
            'net_margin_alert_threshold_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
