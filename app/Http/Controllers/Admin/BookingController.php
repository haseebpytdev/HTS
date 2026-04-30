<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Booking\AmendBookingAction;
use App\Actions\Booking\CreateBookingFromQuotationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AmendBookingRequest;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Http\Requests\Admin\FilterBookingRequest;
use App\Http\Requests\Admin\HoldBookingRequest;
use App\Http\Requests\Admin\UpdateBookingSupplierCostRequest;
use App\Http\Requests\Admin\AssignBookingCustomerRequest;
use App\Models\AsyncTaskRun;
use App\Models\BookingDocument;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Quotation;
use App\Repositories\BookingRepository;
use App\Services\Booking\BookingLifecycleManager;
use App\Services\Booking\BookingService;
use App\Services\Payment\BookingPaymentPanelBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly CreateBookingFromQuotationAction $createBookingFromQuotationAction,
        private readonly BookingLifecycleManager $bookingLifecycleManager,
        private readonly BookingService $bookingService,
        private readonly AmendBookingAction $amendBookingAction,
        private readonly BookingPaymentPanelBuilder $bookingPaymentPanelBuilder,
    ) {
    }

    public function index(FilterBookingRequest $request): View
    {
        $this->authorize('access-admin-area');

        $filters = $request->validated();

        return view('admin.bookings.index', [
            'bookings' => $this->bookingRepository->paginateForAdmin($filters),
            'filters' => $filters,
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $booking);
        $booking->load(['agency', 'quotation', 'items', 'travelers', 'statusHistories.user', 'customer', 'documents.uploadedByUser', 'documents.validatedByUser']);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'paymentPanel' => $this->bookingPaymentPanelBuilder->forBooking($booking),
            'documentScanTasks' => AsyncTaskRun::query()
                ->where('task_type', 'document_virus_scan')
                ->where('reference_type', (new BookingDocument)->getMorphClass())
                ->whereIn('reference_id', $booking->documents->pluck('id')->all())
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function storeFromQuotation(Quotation $quotation): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $quotation);

        $booking = $this->createBookingFromQuotationAction->execute($quotation);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Booking created in draft. Place on hold, then confirm when ready.');
    }

    public function hold(HoldBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $expires = $request->validated('hold_expires_at')
            ? \Illuminate\Support\Carbon::parse($request->validated('hold_expires_at'))
            : null;

        $this->bookingLifecycleManager->placeOnHold($booking, $expires);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Booking placed on hold.');
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $this->bookingLifecycleManager->confirm($booking);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Booking confirmed. Supplier hooks queued (see statuses).');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $this->bookingLifecycleManager->cancel($booking, $request->validated('reason'));

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Booking cancelled.');
    }

    public function amend(AmendBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $this->amendBookingAction->execute($booking, $request->validated());

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Booking updated.');
    }

    public function voucher(Booking $booking): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $booking);
        $booking->load(['agency', 'quotation', 'items', 'travelers']);

        return view('admin.bookings.voucher', [
            'booking' => $booking,
            'renderMode' => 'voucher',
        ]);
    }

    public function invoice(Booking $booking): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $booking);
        $booking = $this->bookingService->ensureInvoiceIssued($booking);
        $booking->load(['agency', 'quotation', 'items', 'travelers']);

        return view('admin.bookings.invoice', [
            'booking' => $booking,
            'renderMode' => 'invoice',
        ]);
    }

    public function assignCustomer(AssignBookingCustomerRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $email = $request->validated('customer_email');
        $customer = Customer::query()->where('email', $email)->firstOrFail();
        $booking->update(['customer_id' => $customer->id]);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Customer portal access linked to '.$customer->email.'.');
    }

    public function updateSupplierCost(UpdateBookingSupplierCostRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $booking);

        $currency = strtoupper((string) ($request->validated('supplier_cost_currency') ?: $booking->currency));
        $booking->update([
            'supplier_cost_total' => number_format((float) $request->validated('supplier_cost_total'), 2, '.', ''),
            'supplier_cost_currency' => $currency,
            'supplier_cost_recorded_at' => now(),
            'supplier_cost_recorded_by_user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Supplier cost captured for true P&L.');
    }
}
