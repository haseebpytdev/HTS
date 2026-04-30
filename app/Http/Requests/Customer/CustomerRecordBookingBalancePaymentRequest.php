<?php

namespace App\Http\Requests\Customer;

use App\Models\Booking;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CustomerRecordBookingBalancePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && auth('customer')->check()
            && $booking->customer_id === auth('customer')->id();
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
            if (! $booking instanceof Booking) {
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
