<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchiveBookingDocumentRequest;
use App\Http\Requests\Admin\StoreBookingDocumentRequest;
use App\Http\Requests\Admin\UpdateBookingDocumentValidationRequest;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Services\Booking\BookingDocumentLifecycleService;
use App\Services\Documents\DocumentScanStateEnforcer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingDocumentController extends Controller
{
    public function __construct(
        private readonly BookingDocumentLifecycleService $lifecycle,
        private readonly DocumentScanStateEnforcer $scanStateEnforcer,
    ) {
    }

    public function store(StoreBookingDocumentRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $this->lifecycle->uploadByAdmin(
            booking: $booking,
            file: $request->file('file'),
            documentType: $request->validated('document_type'),
            isCustomerVisible: (bool) $request->boolean('is_customer_visible', true),
            notes: $request->validated('notes'),
            actor: auth()->user(),
        );

        return back()->with('success', 'Document uploaded.');
    }

    public function validateDocument(
        UpdateBookingDocumentValidationRequest $request,
        Booking $booking,
        BookingDocument $document
    ): RedirectResponse {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        $status = $request->validated('validation_status');

        $this->lifecycle->validateByAdmin(
            document: $document,
            status: $status,
            isCustomerVisible: (bool) $request->boolean('is_customer_visible', $document->is_customer_visible),
            validationNote: $request->validated('validation_note'),
            actor: auth()->user(),
        );

        return back()->with('success', 'Document validation updated.');
    }

    public function download(Booking $booking, BookingDocument $document): StreamedResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $booking);
        $document = BookingDocument::withTrashed()->findOrFail($document->id);
        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        if (! $this->scanStateEnforcer->canDownload($document, 'admin')) {
            $reason = $this->scanStateEnforcer->blockedReason($document);
            $this->lifecycle->recordBlockedDownloadByAdmin($document, auth()->user(), $reason);
            abort(423, $reason);
        }

        if (! Storage::disk($document->storage_disk)->exists($document->storage_path)) {
            abort(404, 'File not found.');
        }

        $this->lifecycle->recordDownloadByAdmin($document, auth()->user());

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_name);
    }

    public function rescan(Booking $booking, BookingDocument $document): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);
        $document = BookingDocument::withTrashed()->findOrFail($document->id);
        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        $this->lifecycle->requestRescanByAdmin($document, auth()->user());

        return back()->with('success', 'Document rescan has been queued.');
    }

    public function archive(ArchiveBookingDocumentRequest $request, Booking $booking, BookingDocument $document): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);
        $document = BookingDocument::withTrashed()->findOrFail($document->id);
        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        $this->lifecycle->archiveByAdmin($document, auth()->user(), $request->validated('archive_note'));

        return back()->with('success', 'Document archived.');
    }
}
