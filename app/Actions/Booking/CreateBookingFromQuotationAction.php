<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\Quotation;
use App\Services\Booking\BookingService;

class CreateBookingFromQuotationAction
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {
    }

    public function execute(Quotation $quotation): Booking
    {
        return $this->bookingService->createDraftFromQuotation($quotation);
    }
}
