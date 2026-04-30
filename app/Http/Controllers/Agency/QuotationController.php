<?php

namespace App\Http\Controllers\Agency;

use App\Actions\Agency\CreateBookingIntentAction;
use App\Actions\Agency\RequestQuotationRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\FilterAgencyQuotationRequest;
use App\Http\Requests\Agency\StoreBookingIntentRequest;
use App\Http\Requests\Agency\StoreRevisionRequest;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly RequestQuotationRevisionAction $requestQuotationRevisionAction,
        private readonly CreateBookingIntentAction $createBookingIntentAction
    ) {
    }

    public function index(FilterAgencyQuotationRequest $request): View
    {
        $this->authorize('access-agency-area');

        $user = $request->user();
        $agencyId = $user?->agency_id;

        $quotations = Quotation::query()
            ->with('agency')
            ->where('agency_id', $agencyId)
            ->when(($request->validated()['q'] ?? null), function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('quote_number', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%");
                });
            })
            ->when(($request->validated()['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('agency.quotations.index', [
            'quotations' => $quotations,
            'filters' => $request->validated(),
        ]);
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('access-agency-area');
        $this->guardOwnership($quotation);

        $quotation->load(['items', 'revisionRequests.user', 'bookingIntents.user']);

        return view('agency.quotations.show', compact('quotation'));
    }

    public function requestRevision(StoreRevisionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('access-agency-area');
        $this->guardOwnership($quotation);

        $this->requestQuotationRevisionAction->execute(
            $quotation,
            (int) $request->user()->agency_id,
            (int) $request->user()->id,
            $request->validated()['message']
        );

        return redirect()->route('agency.quotations.show', $quotation)
            ->with('success', 'Revision request submitted.');
    }

    public function bookingIntent(StoreBookingIntentRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('access-agency-area');
        $this->guardOwnership($quotation);

        $this->createBookingIntentAction->execute(
            $quotation,
            (int) $request->user()->agency_id,
            (int) $request->user()->id,
            $request->validated()['note'] ?? null
        );

        return redirect()->route('agency.quotations.show', $quotation)
            ->with('success', 'Booking intent submitted.');
    }

    private function guardOwnership(Quotation $quotation): void
    {
        if ((int) $quotation->agency_id !== (int) auth()->user()?->agency_id) {
            abort(403);
        }
    }
}
