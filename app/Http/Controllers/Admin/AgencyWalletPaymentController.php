<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgencyWalletTopUpRequest;
use App\Models\Agency;
use App\Services\Finance\FinanceSettingsService;
use App\Services\Payment\Exceptions\PaymentProcessingException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;

class AgencyWalletPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly FinanceSettingsService $financeSettings,
    ) {}

    public function storeTopUp(AgencyWalletTopUpRequest $request, Agency $agency): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $agency);
        if (! $this->financeSettings->isManualLedgerAdjustmentAllowed()) {
            return back()->withInput()->with('error', 'Manual ledger adjustments are disabled by Super Admin finance policy.');
        }

        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $currency = strtoupper($data['currency'] ?? 'PKR');

        try {
            $payment = $this->paymentService->recordWalletTopUp(
                $agency,
                $amount,
                $request->user(),
                $data['idempotency_key'] ?? null,
                $currency,
            );

            if ($payment->status === PaymentRecordStatus::Failed->value) {
                return back()
                    ->withInput()
                    ->with('error', 'Top-up did not complete. Check gateway configuration or try again.');
            }
        } catch (PaymentProcessingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Wallet top-up recorded.');
    }
}
