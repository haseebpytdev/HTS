<?php

namespace App\Http\Requests\Admin;

use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordBookingFullPaymentRequest extends FormRequest
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
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $booking = $this->route('booking');
            if (! $booking instanceof \App\Models\Booking) {
                return;
            }

            $due = app(PaymentService::class)->balanceDueForBooking($booking);
            if (bccomp($due, '0', 2) <= 0) {
                $v->errors()->add('idempotency_key', 'Nothing is due for this booking.');
            }
        });
    }
}
