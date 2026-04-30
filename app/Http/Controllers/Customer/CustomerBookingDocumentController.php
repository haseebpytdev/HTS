<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Services\Customer\CustomerBookingAccess;
use Illuminate\View\View;

class CustomerBookingDocumentController extends Controller
{
    public function __construct(
        private readonly CustomerBookingAccess $bookingAccess,
        private readonly BookingService $bookingService,
    ) {}

    public function voucher(Booking $booking): View
    {
        $this->bookingAccess->ensure(auth('customer')->user(), $booking);
        $booking->load(['agency', 'quotation', 'items', 'travelers']);

        return view('admin.bookings.voucher', [
            'booking' => $booking,
            'renderMode' => 'voucher',
        ]);
    }

    public function invoice(Booking $booking): View
    {
        $this->bookingAccess->ensure(auth('customer')->user(), $booking);
        $booking = $this->bookingService->ensureInvoiceIssued($booking);
        $booking->load(['agency', 'quotation', 'items', 'travelers']);

        return view('admin.bookings.invoice', [
            'booking' => $booking,
            'renderMode' => 'invoice',
        ]);
    }
}
