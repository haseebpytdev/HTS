<?php

namespace Tests\Feature\Frontend;

use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\Stub\StubBookingAdapter;
use App\Integrations\Stub\StubFlightPriceAdapter;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\IntegrationConnection;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use App\Services\Integrations\BookingRevalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FlightResultProceedFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $directory = public_path('data');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($directory.'/airports.json', json_encode([
            ['iata' => 'LHR', 'airport' => 'Heathrow Airport', 'city' => 'London', 'country' => 'United Kingdom'],
            ['iata' => 'JFK', 'airport' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'United States'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $tenant = Tenant::query()->create([
            'name' => 'Default Tenant',
            'slug' => 'default',
            'plan_tier' => 'enterprise',
        ]);

        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'stub',
            'environment' => 'production',
            'can_search' => true,
            'can_price' => true,
            'can_book' => true,
            'allow_multi_provider' => false,
            'allow_fallback' => false,
            'priority_order' => 1,
            'is_enabled' => true,
        ]);

        IntegrationConnection::query()->create([
            'name' => 'Stub Connection',
            'provider' => 'stub',
            'environment' => 'production',
            'base_url' => 'https://stub.example.test',
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
            'tenant_id' => $tenant->id,
        ]);

        Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'is_active' => true]);
        Currency::query()->create(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'is_active' => true]);
        ExchangeRate::query()->create([
            'base_currency_code' => 'USD',
            'target_currency_code' => 'PKR',
            'rate' => 278.5,
            'effective_at' => now(),
            'is_active' => true,
        ]);

        config()->set('integrations.driver', 'stub');
        config()->set('integrations.supported_drivers', ['stub']);

        SupplierSearchSession::query()->create([
            'correlation_id' => 'frontend-flow-correlation',
            'provider' => 'stub',
            'environment' => 'production',
            'internal_request_snapshot' => [
                'origin' => 'LHR',
                'destination' => 'JFK',
                'departure_date' => '2026-04-18',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            'search_results_summary' => ['offer_count' => 1],
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $session = SupplierSearchSession::query()->where('correlation_id', 'frontend-flow-correlation')->firstOrFail();
        SupplierOfferSnapshot::query()->create([
            'supplier_search_session_id' => $session->id,
            'offer_key' => 'offer-frontend-1',
            'provider_offer_reference' => 'FRONTEND-OFFER-1',
            'normalized_offer' => [
                'id' => 'offer-frontend-1',
                'provider_offer_reference' => 'FRONTEND-OFFER-1',
                'cabin_summary' => 'ECONOMY',
                'segments' => [[
                    'departure_airport' => 'LHR',
                    'arrival_airport' => 'JFK',
                    'departure_at' => '2026-04-18T08:00:00Z',
                    'arrival_at' => '2026-04-18T15:00:00Z',
                    'marketing_carrier' => 'ST',
                    'operating_carrier' => 'ST',
                    'flight_number' => '101',
                    'cabin_class' => 'economy',
                ]],
                'price' => [
                    'currency' => 'USD',
                    'base_amount' => 400,
                    'tax_amount' => 80,
                    'fee_amount' => 20,
                    'total_amount' => 500,
                    'status' => 'available',
                    'offer_reference' => 'FRONTEND-OFFER-1',
                ],
            ],
            'selected_fare_summary' => null,
            'is_selected' => false,
        ]);

        $this->app->bind(StubFlightPriceAdapter::class, static function (): FlightPricingProviderInterface {
            return new class implements FlightPricingProviderInterface
            {
                public function providerCode(): string
                {
                    return 'stub';
                }

                public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
                {
                    return new PriceBreakdownData(
                        currency: 'USD',
                        baseAmount: 420,
                        taxAmount: 70,
                        feeAmount: 20,
                        totalAmount: 510,
                        status: 'available',
                        offerReference: $offerReference,
                    );
                }
            };
        });

        $this->app->bind(StubBookingAdapter::class, static function (): BookingProviderInterface {
            return new class implements BookingProviderInterface
            {
                public function providerCode(): string
                {
                    return 'stub';
                }

                public function createBooking(BookingCreateRequestData $request): BookingData
                {
                    return new BookingData(
                        status: 'confirmed',
                        providerCode: 'stub',
                        bookingReference: 'BOOK-123',
                        pnr: 'PNR123',
                        travelers: $request->travelers,
                        totalPrice: new PriceBreakdownData(
                            currency: 'USD',
                            baseAmount: 410,
                            taxAmount: 70,
                            feeAmount: 20,
                            totalAmount: 500,
                            status: 'available',
                            offerReference: $request->offerReference,
                        ),
                        createdAt: now()->toIso8601String(),
                    );
                }

                public function retrieveBooking(string $bookingReference): BookingData
                {
                    return new BookingData(status: 'confirmed', providerCode: 'stub', bookingReference: $bookingReference);
                }

                public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
                {
                    return new BookingData(status: 'cancelled', providerCode: 'stub', bookingReference: $bookingReference);
                }
            };
        });
    }

    public function test_continue_action_revalidates_and_moves_to_booking_preparation_flow(): void
    {
        $proceedResponse = $this->post(route('frontend.flights.proceed'), [
            'correlation_id' => 'frontend-flow-correlation',
            'offer_reference' => 'FRONTEND-OFFER-1',
            'provider' => 'stub',
        ]);

        $proceedResponse->assertRedirect(route('frontend.flights.booking.review'));
        $guardSnapshot = app(BookingRevalidationGuard::class)->snapshot('FRONTEND-OFFER-1', 'stub');
        $this->assertSame('available', $guardSnapshot['status'] ?? null);

        $reviewPage = $this->get(route('frontend.flights.booking.review'));
        $reviewPage->assertOk();
        $reviewPage->assertSee('Review your flight');
        $reviewPage->assertSee('Price updated');
        $reviewPage->assertSee('Continue to traveler details');

        $continueResponse = $this->post(route('frontend.flights.booking.continue'));
        $continueResponse->assertRedirect(route('frontend.flights.booking.show'));

        $bookingPage = $this->get(route('frontend.flights.booking.show'));
        $bookingPage->assertOk();
        $bookingPage->assertSee('Traveler details');
        $bookingPage->assertSee('Book now');

        $bookResponse = $this->post(route('frontend.flights.book'), [
            'contact_email' => 'traveler@example.com',
            'contact_phone' => '+923001234567',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Ayesha',
                'family_name' => 'Khan',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'PK',
            ]],
        ]);
        $bookResponse->assertRedirect(route('frontend.flights.booking.show'));

        $confirmationPage = $this->get(route('frontend.flights.booking.show'));
        $confirmationPage->assertSee('Booking created');
        $confirmationPage->assertSee('BOOK-123');
    }
}
