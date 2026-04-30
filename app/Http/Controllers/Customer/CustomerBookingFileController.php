<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Services\Booking\BookingDocumentLifecycleService;
use App\Services\Customer\CustomerBookingAccess;
use App\Services\Documents\DocumentScanStateEnforcer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerBookingFileController extends Controller
{
    public function __construct(
        private readonly CustomerBookingAccess $bookingAccess,
        private readonly BookingDocumentLifecycleService $lifecycle,
        private readonly DocumentScanStateEnforcer $scanStateEnforcer,
    ) {
    }

    public function download(Booking $booking, BookingDocument $document): StreamedResponse
    {
        $customer = auth('customer')->user();
        $this->bookingAccess->ensure($customer, $booking);

        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        if (! $document->is_customer_visible || $document->validation_status !== 'approved') {
            abort(403, 'Document is not available for customer download.');
        }

        if (! $this->scanStateEnforcer->canDownload($document, 'customer')) {
            $reason = $this->scanStateEnforcer->blockedReason($document);
            $this->lifecycle->recordBlockedDownloadByCustomer($document, $customer, $reason);
            abort(423, $reason);
        }

        if (! Storage::disk($document->storage_disk)->exists($document->storage_path)) {
            abort(404, 'File not found.');
        }

        $this->lifecycle->recordDownloadByCustomer($document, $customer);

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_name);
    }
}
