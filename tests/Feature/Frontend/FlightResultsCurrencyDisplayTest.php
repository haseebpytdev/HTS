<?php

namespace Tests\Feature\Frontend;

use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\Duffel\DuffelFlightSearchAdapter;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightResultsCurrencyDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::query()->create([
            'name' => 'Default Tenant',
            'slug' => 'default',
            'plan_tier' => 'enterprise',
        ]);

        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'environment' => 'production',
            'can_search' => true,
            'can_price' => false,
            'can_book' => false,
            'allow_multi_provider' => false,
            'allow_fallback' => false,
            'priority_order' => 1,
            'is_enabled' => true,
        ]);

        IntegrationConnection::query()->create([
            'name' => 'Duffel Search Connection',
            'provider' => 'duffel',
            'environment' => 'production',
            'base_url' => 'https://duffel.example.test',
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
            'tenant_id' => $tenant->id,
        ]);

        foreach (['USD', 'PKR', 'GBP'] as $code) {
            Currency::query()->create([
                'code' => $code,
                'name' => $code,
                'is_active' => true,
            ]);
        }

        ExchangeRate::query()->create([
            'base_currency_code' => 'USD',
            'target_currency_code' => 'PKR',
            'rate' => 278.5,
            'effective_at' => now(),
            'is_active' => true,
        ]);

        ExchangeRate::query()->create([
            'base_currency_code' => 'USD',
            'target_currency_code' => 'GBP',
            'rate' => 0.79,
            'effective_at' => now(),
            'is_active' => true,
        ]);

        config()->set('integrations.driver', 'duffel');
        config()->set('integrations.supported_drivers', ['duffel']);

        $this->app->bind(DuffelFlightSearchAdapter::class, static function (): FlightSearchProviderInterface {
            return new class implements FlightSearchProviderInterface
            {
                public function providerCode(): string
                {
                    return 'duffel';
                }

                public function searchFlights(FlightSearchRequestData $request): array
                {
                    return [
                        new FlightOfferData(
                            id: 'offer-1',
                            providerOfferReference: 'DUFFEL-REF-1',
                            segments: [
                                new FlightSegmentData(
                                    departureAirport: $request->origin,
                                    arrivalAirport: $request->destination,
                                    departureAt: $request->departureDate.'T09:00:00Z',
                                    arrivalAt: $request->departureDate.'T15:00:00Z',
                                    marketingCarrier: 'PK',
                                    flightNumber: '301',
                                    cabinClass: 'economy',
                                ),
                            ],
                            price: new PriceBreakdownData(
                                currency: 'USD',
                                baseAmount: 80.00,
                                taxAmount: 15.00,
                                feeAmount: 5.00,
                                totalAmount: 100.00,
                                status: 'available',
                                offerReference: 'DUFFEL-REF-1',
                            ),
                            cabinSummary: 'ECONOMY',
                        ),
                    ];
                }
            };
        });
    }

    public function test_session_selected_currency_overrides_user_preference(): void
    {
        $user = User::factory()->create([
            'preferred_currency' => 'GBP',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['display_currency' => 'PKR'])
            ->get(route('frontend.flights.results', $this->searchQuery()));

        $response->assertOk();
        $response->assertSee('Display currency:');
        $response->assertSee('PKR');
        $response->assertSeeInOrder(['PKR', '28,407.00'], false);
        $response->assertDontSee('Supplier fare:');
    }

    public function test_logged_in_user_preference_is_used_when_session_currency_is_missing(): void
    {
        $user = User::factory()->create([
            'preferred_currency' => 'GBP',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('frontend.flights.results', $this->searchQuery()));

        $response->assertOk();
        $response->assertSee('Display currency:');
        $response->assertSee('GBP');
        $response->assertSeeInOrder(['GBP', '80.58'], false);
        $response->assertDontSee('Supplier fare:');
    }

    public function test_ip_country_currency_guess_is_used_when_no_user_or_session_selection_exists(): void
    {
        $response = $this
            ->withHeaders(['CF-IPCountry' => 'GB'])
            ->get(route('frontend.flights.results', $this->searchQuery()));

        $response->assertOk();
        $response->assertSee('Display currency:');
        $response->assertSee('GBP');
        $response->assertSeeInOrder(['GBP', '80.58'], false);
        $response->assertDontSee('Supplier fare:');
    }

    public function test_missing_exchange_rate_shows_placeholder_in_display_currency(): void
    {
        ExchangeRate::query()->delete();

        $response = $this->get(route('frontend.flights.results', $this->searchQuery()));

        $response->assertOk();
        $response->assertSee('Display currency:');
        $response->assertSee('PKR');
        $response->assertSeeInOrder(['PKR', '—'], false);
        $response->assertSee('Could not load a display estimate right now');
        $response->assertDontSee('Supplier fare:');
    }

    public function test_display_currency_query_parameter_overrides_session_selection(): void
    {
        $user = User::factory()->create([
            'preferred_currency' => 'USD',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['display_currency' => 'PKR'])
            ->get(route('frontend.flights.results', array_merge($this->searchQuery(), [
                'display_currency' => 'GBP',
            ])));

        $response->assertOk();
        $response->assertSee('Show prices in');
        $response->assertSee('Display currency:');
        $response->assertSee('GBP');
        $response->assertSeeInOrder(['GBP', '80.58'], false);
        $response->assertDontSee('Supplier fare:');
    }

    /**
     * @return array<string, string>
     */
    private function searchQuery(): array
    {
        return [
            'trip_type' => 'one_way',
            'from' => 'London, United Kingdom - Heathrow (LHR)',
            'to' => 'New York, United States - John F. Kennedy International (JFK)',
            'origin' => 'LHR',
            'destination' => 'JFK',
            'departure_date' => '2026-04-18',
            'passengers' => '2',
            'cabin_class' => 'economy',
        ];
    }
}
