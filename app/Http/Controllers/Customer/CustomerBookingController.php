<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Services\Customer\CustomerBookingAccess;
use App\Services\Payment\BookingPaymentPanelBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CustomerBookingController extends Controller
{
    public function __construct(
        private readonly CustomerBookingAccess $bookingAccess,
        private readonly BookingPaymentPanelBuilder $paymentPanelBuilder,
    ) {}

    public function index(): View
    {
        /** @var LengthAwarePaginator<int, Booking> $bookings */
        $bookings = Booking::query()
            ->where('customer_id', auth('customer')->id())
            ->orderByDesc('id')
            ->paginate(15);

        return view('customer.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking): View
    {
        $this->bookingAccess->ensure(auth('customer')->user(), $booking);

        $booking->load(['agency', 'quotation', 'items', 'travelers']);
        $customerVisibleDocuments = BookingDocument::query()
            ->where('booking_id', $booking->id)
            ->where('is_customer_visible', true)
            ->where('validation_status', 'approved')
            ->where('virus_scan_status', BookingDocument::SCAN_CLEAN)
            ->orderByDesc('id')
            ->get();

        return view('customer.bookings.show', [
            'booking' => $booking,
            'paymentPanel' => $this->paymentPanelBuilder->forBooking($booking),
            'customerDocuments' => $customerVisibleDocuments,
        ]);
    }
}
