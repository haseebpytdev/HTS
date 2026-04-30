<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundRecordedPaymentRequest;
use App\Models\Payment;
use App\Services\Payment\Exceptions\PaymentProcessingException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;

class PaymentRefundController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function store(RefundRecordedPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');

        try {
            $refund = $this->paymentService->refundExternalPayment(
                $payment,
                $amount,
                $data['reason'] ?? null,
            );

            if ($refund->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Refund did not complete. Check gateway response or try again.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $redirect = $payment->booking_id
            ? redirect()->route('admin.bookings.show', $payment->booking_id)
            : redirect()->route('admin.agencies.edit', $payment->agency_id);

        return $redirect->with('success', 'Refund submitted.');
    }
}
