<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentSource;
use App\Models\Payment;
use App\Services\Finance\FinanceSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RefundRecordedPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('access-admin-area');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $payment = $this->route('payment');
            if (! $payment instanceof Payment) {
                return;
            }

            if ($payment->status !== PaymentRecordStatus::Completed->value) {
                $v->errors()->add('amount', 'Only completed payments can be refunded.');

                return;
            }

            if ($payment->source !== PaymentSource::ExternalGateway->value) {
                $v->errors()->add('amount', 'Only external gateway payments use this refund flow.');

                return;
            }

            $finance = app(FinanceSettingsService::class);
            if ($finance->isRefundReasonRequired() && trim((string) $this->input('reason', '')) === '') {
                $v->errors()->add('reason', 'A refund reason is required by current finance policy.');
            }

            $maxDays = $finance->refundMaxDaysSincePayment();
            if ($maxDays > 0 && $payment->created_at !== null && $payment->created_at->lt(now()->subDays($maxDays))) {
                $v->errors()->add('amount', 'Payment is older than the allowed refund window ('.$maxDays.' days).');
            }

            $amount = number_format((float) $this->input('amount'), 2, '.', '');
            $original = number_format((float) $payment->amount, 2, '.', '');

            if (bccomp($amount, $original, 2) > 0) {
                $v->errors()->add('amount', 'Refund cannot exceed original payment amount.');
            }
        });
    }
}
