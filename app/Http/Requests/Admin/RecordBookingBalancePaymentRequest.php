<?php

namespace App\Http\Requests\Admin;

use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordBookingBalancePaymentRequest extends FormRequest
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
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $booking = $this->route('booking');
            if (! $booking instanceof \App\Models\Booking) {
                return;
            }

            $due = app(PaymentService::class)->balanceDueForBooking($booking);
            $amount = number_format((float) $this->input('amount'), 2, '.', '');

            if (bccomp($due, '0', 2) <= 0) {
                $v->errors()->add('amount', 'Nothing is due for this booking.');

                return;
            }

            if (bccomp($amount, $due, 2) > 0) {
                $v->errors()->add('amount', 'Amount cannot exceed balance due.');
            }
        });
    }
}
