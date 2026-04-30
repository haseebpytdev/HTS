<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayBookingFromWalletRequest;
use App\Http\Requests\Admin\RecordBookingBalancePaymentRequest;
use App\Http\Requests\Admin\RecordBookingDepositRequest;
use App\Http\Requests\Admin\RecordBookingFullPaymentRequest;
use App\Models\Booking;
use App\Services\Finance\FinanceSettingsService;
use App\Services\Payment\Exceptions\PaymentProcessingException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;

class BookingPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly FinanceSettingsService $financeSettings,
    ) {}

    public function storeDeposit(RecordBookingDepositRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $bookingTotal = (float) ($booking->total_amount ?? 0);
        $minimumDeposit = $this->financeSettings->minimumDepositAmount($bookingTotal);
        if ((float) $amount < $minimumDeposit) {
            return back()
                ->withInput()
                ->with('error', 'Deposit cannot be lower than platform minimum of '.number_format($minimumDeposit, 2).'.');
        }

        try {
            $payment = $this->paymentService->recordDeposit(
                $booking,
                $amount,
                $request->user(),
                $data['idempotency_key'] ?? null,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Payment was not completed. Check gateway configuration or try again.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Deposit recorded.');
    }

    public function storeBalancePayment(RecordBookingBalancePaymentRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);
        $controls = $this->financeSettings->all();
        if (! (bool) ($controls['deposit_allow_balance_installments'] ?? true)) {
            return back()->withInput()->with('error', 'Balance installment payments are disabled by current finance policy.');
        }

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');

        try {
            $payment = $this->paymentService->recordBalancePayment(
                $booking,
                $amount,
                $request->user(),
                $data['idempotency_key'] ?? null,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Payment was not completed. Check gateway configuration or try again.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Balance payment recorded.');
    }

    public function storeFullPayment(RecordBookingFullPaymentRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $data = $request->validated();

        try {
            $payment = $this->paymentService->recordFullPayment(
                $booking,
                $request->user(),
                $data['idempotency_key'] ?? null,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Payment was not completed. Check gateway configuration or try again.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Full payment recorded.');
    }

    public function storeWalletPayment(PayBookingFromWalletRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');

        try {
            $this->paymentService->payBookingFromWallet(
                $booking,
                $amount,
                $request->user(),
                $data['idempotency_key'] ?? null,
            );
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Booking paid from agency wallet.');
    }
}
