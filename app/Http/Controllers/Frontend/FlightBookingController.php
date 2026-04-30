<?php

namespace App\Http\Controllers\Frontend;

use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\TravelerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\SelectFrontendFlightOfferRequest;
use App\Http\Requests\Frontend\StoreFrontendFlightBookingRequest;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use App\Services\Currency\DisplayCurrencyResolver;
use App\Services\Integrations\BookingOrchestrator;
use App\Services\Integrations\BookingRevalidationGuard;
use App\Services\Integrations\FlightPricingOrchestrator;
use App\ViewModels\Frontend\FlightSearchResultViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Public flight booking steps after search. Selecting “Continue” runs server-side
 * fare revalidation; the user reviews the refreshed itinerary and price before
 * traveler details (no separate manual revalidate step).
 */
class FlightBookingController extends Controller
{
    private const SELECTED_OFFER_SESSION_KEY = 'frontend.flights.selected_offer';

    public function __construct(
        private readonly DisplayCurrencyResolver $displayCurrencyResolver,
        private readonly FlightSearchResultViewModel $resultViewModel,
        private readonly FlightPricingOrchestrator $flightPricingOrchestrator,
        private readonly BookingRevalidationGuard $bookingRevalidationGuard,
        private readonly BookingOrchestrator $bookingOrchestrator,
    ) {
    }

    public function proceed(SelectFrontendFlightOfferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $displayCurrency = $this->displayCurrencyResolver->resolve($request)['currency'];
        $selection = $this->resolveOfferSelection(
            correlationId: (string) $validated['correlation_id'],
            offerReference: (string) $validated['offer_reference'],
            provider: isset($validated['provider']) ? (string) $validated['provider'] : null,
            displayCurrency: $displayCurrency,
        );

        try {
            $this->revalidateSelection($selection, $displayCurrency);
            $this->persistSelection($selection, $request);
            $this->updateOfferSnapshotSelection($selection, $selection['selected_fare_summary']);

            return redirect()->route('frontend.flights.booking.review')
                ->with('flight_status', 'We confirmed the latest fare with the airline. Please review the details below before entering traveler information.');
        } catch (SupplierIntegrationException $exception) {
            return redirect()->back()->with('flight_error', $exception->getMessage());
        } catch (Throwable) {
            return redirect()->back()->with('flight_error', 'Unable to confirm this fare right now. Please try again.');
        }
    }

    public function showBookingReview(): View
    {
        return view('frontend.flights.booking-review', [
            'selection' => $this->selectedOfferFromSession(),
        ]);
    }

    public function continueToBooking(): RedirectResponse
    {
        $selection = $this->selectedOfferFromSession();

        try {
            $this->bookingRevalidationGuard->enforceFreshSuccessfulRevalidation(
                (string) $selection['offer_reference'],
                (string) $selection['provider']
            );
        } catch (SupplierIntegrationException $exception) {
            return redirect()->route('frontend.flights.booking.review')
                ->with('flight_error', $exception->getMessage());
        }

        return redirect()->route('frontend.flights.booking.show');
    }

    public function showBookingForm(): View|RedirectResponse
    {
        $selection = $this->selectedOfferFromSession();

        try {
            $this->bookingRevalidationGuard->enforceFreshSuccessfulRevalidation(
                (string) $selection['offer_reference'],
                (string) $selection['provider']
            );
        } catch (SupplierIntegrationException $exception) {
            return redirect()->route('frontend.flights.booking.review')
                ->with('flight_error', $exception->getMessage());
        }

        return view('frontend.flights.continue', [
            'selection' => $selection,
            'travelerForms' => $this->travelerForms($selection),
            'bookingResult' => session('flight_booking_result'),
        ]);
    }

    public function book(StoreFrontendFlightBookingRequest $request): RedirectResponse
    {
        $selection = $this->selectedOfferFromSession();

        try {
            $this->bookingRevalidationGuard->enforceFreshSuccessfulRevalidation(
                (string) $selection['offer_reference'],
                (string) $selection['provider']
            );
        } catch (SupplierIntegrationException $exception) {
            return redirect()->route('frontend.flights.booking.review')
                ->with('flight_error', $exception->getMessage());
        }

        $validated = $request->validated();
        $travelers = [];
        foreach ($validated['travelers'] as $travelerRow) {
            $travelers[] = new TravelerData(
                travelerType: (string) $travelerRow['traveler_type'],
                givenName: (string) $travelerRow['given_name'],
                familyName: (string) $travelerRow['family_name'],
                dateOfBirth: $travelerRow['date_of_birth'] ?? null,
                nationality: isset($travelerRow['nationality']) ? strtoupper((string) $travelerRow['nationality']) : null,
            );
        }

        try {
            $booking = $this->bookingOrchestrator->create(
                new BookingCreateRequestData(
                    offerReference: (string) $selection['offer_reference'],
                    travelers: $travelers,
                    contactEmail: $validated['contact_email'] ?? null,
                    contactPhone: $validated['contact_phone'] ?? null,
                ),
                correlationId: (string) $selection['correlation_id'],
                providerOverride: (string) $selection['provider'],
            );

            return redirect()->route('frontend.flights.booking.show')
                ->with('flight_status', 'Booking request submitted successfully.')
                ->with('flight_booking_result', $booking->jsonSerialize());
        } catch (SupplierIntegrationException $exception) {
            return redirect()->route('frontend.flights.booking.show')
                ->withInput()
                ->with('flight_error', $exception->getMessage());
        } catch (Throwable) {
            return redirect()->route('frontend.flights.booking.show')
                ->withInput()
                ->with('flight_error', 'Booking is temporarily unavailable. Please try again.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOfferSelection(string $correlationId, string $offerReference, ?string $provider, string $displayCurrency): array
    {
        $session = SupplierSearchSession::query()->where('correlation_id', $correlationId)->firstOrFail();

        $snapshot = SupplierOfferSnapshot::query()
            ->where('supplier_search_session_id', $session->id)
            ->where(function ($query) use ($offerReference): void {
                $query->where('provider_offer_reference', $offerReference)
                    ->orWhere('offer_key', $offerReference);
            })->firstOrFail();

        $resolvedProvider = strtolower((string) ($provider ?: $session->provider));
        $resolvedOfferReference = trim((string) ($snapshot->provider_offer_reference ?: $snapshot->offer_key));
        $normalizedOffer = is_array($snapshot->normalized_offer) ? $snapshot->normalized_offer : [];

        return [
            'correlation_id' => $correlationId,
            'provider' => $resolvedProvider,
            'offer_reference' => $resolvedOfferReference,
            'offer_key' => (string) $snapshot->offer_key,
            'offer' => $this->resultViewModel->present($normalizedOffer, $displayCurrency),
            'selected_fare_summary' => is_array($snapshot->selected_fare_summary) ? $snapshot->selected_fare_summary : null,
            'search_request' => is_array($session->internal_request_snapshot) ? $session->internal_request_snapshot : [],
            'revalidation' => $this->bookingRevalidationGuard->snapshot($resolvedOfferReference, $resolvedProvider),
        ];
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function revalidateSelection(array &$selection, string $displayCurrency): void
    {
        $offerBefore = is_array($selection['offer'] ?? null) ? $selection['offer'] : [];
        $prevTotal = (float) data_get($offerBefore, 'price.total_amount', 0);
        $prevCurrency = strtoupper(trim((string) data_get($offerBefore, 'price.currency', '')));

        $result = $this->flightPricingOrchestrator->revalidateFareWithFallback(
            offerReference: (string) $selection['offer_reference'],
            opaqueContext: [
                'selected_offer' => $selection['offer'],
                'selected_offer_reference' => $selection['offer_reference'],
            ],
            correlationId: (string) $selection['correlation_id'],
            providerOverride: (string) $selection['provider'],
            providers: [(string) $selection['provider']],
            allowFallback: false,
        );

        $priceData = $result['price'];
        $this->bookingRevalidationGuard->recordRevalidationResult(
            offerReference: (string) $selection['offer_reference'],
            driver: (string) $selection['provider'],
            status: $priceData->status,
            correlationId: (string) $selection['correlation_id'],
            totalAmount: $priceData->totalAmount,
            currency: $priceData->currency,
        );

        $selection['selected_fare_summary'] = $priceData->jsonSerialize();

        $newCurrency = strtoupper(trim($priceData->currency));
        $newTotal = $priceData->totalAmount;
        $selection['fare_comparison'] = [
            'price_changed' => abs($prevTotal - $newTotal) > 0.009 || $prevCurrency !== $newCurrency,
            'search_total' => $prevTotal,
            'search_currency' => $prevCurrency,
            'confirmed_total' => $newTotal,
            'confirmed_currency' => $newCurrency,
        ];

        $normalizedOffer = is_array($selection['offer'] ?? null) ? $selection['offer'] : [];
        $priceRow = is_array($normalizedOffer['price'] ?? null) ? $normalizedOffer['price'] : [];
        $fare = $priceData->jsonSerialize();
        $normalizedOffer['price'] = array_merge($priceRow, array_intersect_key(
            $fare,
            array_flip([
                'currency', 'base_amount', 'tax_amount', 'fee_amount', 'total_amount', 'status', 'offer_reference', 'lines',
            ])
        ));
        $selection['offer'] = $this->resultViewModel->present($normalizedOffer, $displayCurrency);

        $selection['revalidation'] = $this->bookingRevalidationGuard->snapshot(
            (string) $selection['offer_reference'],
            (string) $selection['provider']
        );
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function persistSelection(array $selection, Request $request): void
    {
        $request->session()->put(self::SELECTED_OFFER_SESSION_KEY, $selection);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>|null  $fareSummary
     */
    private function updateOfferSnapshotSelection(array $selection, ?array $fareSummary): void
    {
        SupplierOfferSnapshot::query()
            ->whereHas('searchSession', function ($query) use ($selection): void {
                $query->where('correlation_id', $selection['correlation_id']);
            })
            ->update(['is_selected' => false]);

        SupplierOfferSnapshot::query()
            ->where('offer_key', (string) $selection['offer_key'])
            ->where('provider_offer_reference', (string) $selection['offer_reference'])
            ->update([
                'is_selected' => true,
                'selected_fare_summary' => $fareSummary,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedOfferFromSession(): array
    {
        $selection = session(self::SELECTED_OFFER_SESSION_KEY);
        abort_unless(is_array($selection), 404);

        return $selection;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<array<string, mixed>>
     */
    private function travelerForms(array $selection): array
    {
        $request = is_array($selection['search_request'] ?? null) ? $selection['search_request'] : [];
        $forms = [];

        foreach (range(1, max(0, (int) ($request['adults'] ?? 0))) as $index) {
            $forms[] = ['traveler_type' => 'adult', 'label' => 'Adult '.$index];
        }
        foreach (range(1, max(0, (int) ($request['children'] ?? 0))) as $index) {
            $forms[] = ['traveler_type' => 'child', 'label' => 'Child '.$index];
        }
        foreach (range(1, max(0, (int) ($request['infants'] ?? 0))) as $index) {
            $forms[] = ['traveler_type' => 'infant', 'label' => 'Infant '.$index];
        }

        return $forms !== [] ? $forms : [['traveler_type' => 'adult', 'label' => 'Adult 1']];
    }
}
