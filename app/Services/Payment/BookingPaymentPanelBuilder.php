<?php

namespace App\Services\Payment;

use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentSource;
use App\Models\AgencyWallet;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Read model for admin booking payment UI (no rules — use PaymentService / LedgerService for writes).
 */
final class BookingPaymentPanelBuilder
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * @return array{
     *   paid_total: string,
     *   balance_due: string,
     *   wallet: AgencyWallet|null,
     *   recent_payments: Collection<int, Payment>,
     *   refundable_payments: Collection<int, Payment>
     * }
     */
    public function forBooking(Booking $booking): array
    {
        $booking->loadMissing(['agency', 'payments.gatewayTransactions']);

        $paid = $this->paymentService->sumCompletedForBooking($booking);
        $due = $this->paymentService->balanceDueForBooking($booking);

        $wallet = null;
        if ($booking->agency_id && $booking->agency) {
            $wallet = $this->ledgerService->ensureWallet($booking->agency);
        }

        $recent = $booking->payments->sortByDesc('id')->take(15)->values();

        $refundable = $recent->filter(
            static fn (Payment $p): bool => $p->status === PaymentRecordStatus::Completed->value
                && $p->source === PaymentSource::ExternalGateway->value
        )->values();

        return [
            'paid_total' => $paid,
            'balance_due' => $due,
            'wallet' => $wallet,
            'recent_payments' => $recent,
            'refundable_payments' => $refundable,
        ];
    }
}
