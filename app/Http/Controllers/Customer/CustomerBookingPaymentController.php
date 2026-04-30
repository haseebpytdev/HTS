<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerRecordBookingBalancePaymentRequest;
use App\Http\Requests\Customer\CustomerRecordBookingDepositRequest;
use App\Http\Requests\Customer\CustomerRecordBookingFullPaymentRequest;
use App\Models\Booking;
use App\Services\Customer\CustomerBookingAccess;
use App\Services\Payment\Exceptions\PaymentProcessingException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;

class CustomerBookingPaymentController extends Controller
{
    public function __construct(
        private readonly CustomerBookingAccess $bookingAccess,
        private readonly PaymentService $paymentService,
    ) {}

    public function storeDeposit(CustomerRecordBookingDepositRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookingAccess->ensure(auth('customer')->user(), $booking);

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');

        try {
            $payment = $this->paymentService->recordDeposit(
                $booking,
                $amount,
                null,
                $data['idempotency_key'] ?? null,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Payment was not completed. Please try again or contact support.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Deposit recorded.');
    }

    public function storeBalancePayment(CustomerRecordBookingBalancePaymentRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookingAccess->ensure(auth('customer')->user(), $booking);

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');

        try {
            $payment = $this->paymentService->recordBalancePayment(
                $booking,
                $amount,
                null,
                $data['idempotency_key'] ?? null,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Payment was not completed. Please try again or contact support.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function storeFullPayment(CustomerRecordBookingFullPaymentRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookingAccess->ensure(auth('customer')->user(), $booking);

        $data = $request->validated();

        try {
            $payment = $this->paymentService->recordFullPayment(
                $booking,
                null,
                $data['idempotency_key'] ?? null,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Payment was not completed. Please try again or contact support.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Full payment recorded.');
    }
}
