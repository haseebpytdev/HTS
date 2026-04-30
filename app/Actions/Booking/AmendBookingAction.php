<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Services\Booking\BookingLifecycleManager;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\DB;

class AmendBookingAction
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingLifecycleManager $lifecycleManager
    ) {
    }

    /**
     * @param  array{internal_notes?: string|null, remarks?: string|null, amend_reason?: string|null, travelers?: list<array<string, mixed>>|null}  $data
     */
    public function execute(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            if (array_key_exists('internal_notes', $data)) {
                $booking->internal_notes = $data['internal_notes'];
            }
            if (array_key_exists('remarks', $data)) {
                $booking->remarks = $data['remarks'];
            }
            $booking->save();

            if (! empty($data['travelers']) && is_array($data['travelers'])) {
                $this->bookingService->syncTravelers($booking, $data['travelers']);
            }

            $this->lifecycleManager->recordAmendment(
                $booking->fresh(),
                $data['amend_reason'] ?? null,
                ['travelers_updated' => ! empty($data['travelers'])]
            );

            return $booking->fresh(['items', 'travelers', 'agency', 'quotation']);
        });
    }
}
