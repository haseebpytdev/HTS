<?php

namespace App\Services\Booking;

use App\Automation\Events\BookingConfirmed;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class BookingLifecycleManager
{
    public function __construct(
        private readonly BookingSupplierIntegrationBridge $supplierIntegrationBridge
    ) {
    }

    public function record(
        Booking $booking,
        ?string $fromStatus,
        string $toStatus,
        ?string $event = null,
        ?string $reason = null,
        array $meta = []
    ): BookingStatusHistory {
        return BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => Auth::guard('web')->id(),
            'event' => $event,
            'reason' => $reason,
            'meta' => $meta ?: null,
        ]);
    }

    public function initializeFromQuotation(Booking $booking): void
    {
        $this->record(
            $booking,
            null,
            $booking->status,
            'created_from_quotation',
            null,
            ['quotation_id' => $booking->quotation_id]
        );
    }

    public function placeOnHold(Booking $booking, ?\DateTimeInterface $holdExpiresAt = null): void
    {
        $this->assertTransition($booking->status, BookingStatus::OnHold->value);

        $from = $booking->status;
        $expires = $holdExpiresAt ?? now()->addHours(72);

        $booking->forceFill([
            'status' => BookingStatus::OnHold->value,
            'hold_expires_at' => $expires,
        ])->save();

        $this->record($booking, $from, BookingStatus::OnHold->value, 'hold', null, [
            'hold_expires_at' => $expires->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function confirm(Booking $booking): void
    {
        $this->assertTransition($booking->status, BookingStatus::Confirmed->value);

        $from = $booking->status;

        $booking->forceFill([
            'status' => BookingStatus::Confirmed->value,
            'confirmed_at' => now(),
            'booked_at' => $booking->booked_at ?? now(),
        ])->save();

        $this->record($booking, $from, BookingStatus::Confirmed->value, 'confirm');

        $this->supplierIntegrationBridge->runPostConfirmHooks($booking->fresh());
        BookingConfirmed::dispatch($booking->fresh());
    }

    public function cancel(Booking $booking, ?string $reason = null): void
    {
        if ($booking->status === BookingStatus::Cancelled->value) {
            return;
        }

        $from = $booking->status;

        $booking->forceFill([
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        $this->record($booking, $from, BookingStatus::Cancelled->value, 'cancel', $reason);

        // Structural readiness for supplier cancellation/change flows (currently metadata-first).
        try {
            $prepared = $this->supplierIntegrationBridge->dispatchFlightCancellationPreparation($booking->fresh(), $reason);
            if ((bool) ($prepared['prepared'] ?? false)) {
                $this->record(
                    $booking->fresh(),
                    BookingStatus::Cancelled->value,
                    BookingStatus::Cancelled->value,
                    'supplier_cancel_prepared',
                    null,
                    [
                        'provider' => $prepared['provider'] ?? null,
                        'supplier_booking_reference' => $prepared['supplier_booking_reference'] ?? null,
                        'approval_request_type' => 'booking_force_cancel',
                        'cancellation' => is_array($prepared['cancellation'] ?? null) ? $prepared['cancellation'] : null,
                        'change_groundwork' => is_array($prepared['change_groundwork'] ?? null) ? $prepared['change_groundwork'] : null,
                    ]
                );
            }
        } catch (\Throwable) {
            // Local cancellation must remain reliable even if supplier preparation fails.
        }
    }

    /**
     * Log a structural change (travelers, items, notes) while keeping status.
     */
    public function recordAmendment(Booking $booking, ?string $reason = null, array $meta = []): void
    {
        $this->record(
            $booking,
            $booking->status,
            $booking->status,
            'amended',
            $reason,
            $meta
        );
    }

    private function assertTransition(string $current, string $target): void
    {
        $allowed = match ($target) {
            BookingStatus::OnHold->value => [
                BookingStatus::Draft->value,
                BookingStatus::Pending->value,
            ],
            BookingStatus::Confirmed->value => [
                BookingStatus::OnHold->value,
                BookingStatus::Draft->value,
                BookingStatus::Pending->value,
            ],
            BookingStatus::Cancelled->value => [
                BookingStatus::Draft->value,
                BookingStatus::OnHold->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Pending->value,
            ],
            default => [],
        };

        if (! in_array($current, $allowed, true)) {
            throw new InvalidArgumentException("Cannot transition booking status from [{$current}] to [{$target}].");
        }
    }
}
