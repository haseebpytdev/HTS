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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FlightResultsUiTest extends TestCase
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
            ['iata' => 'IST', 'airport' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
            ['iata' => 'JFK', 'airport' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'United States'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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

        Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'is_active' => true]);
        Currency::query()->create(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'is_active' => true]);
        ExchangeRate::query()->create([
            'base_currency_code' => 'USD',
            'target_currency_code' => 'PKR',
            'rate' => 278.5,
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
                            id: 'offer-ui-1',
                            providerOfferReference: 'DUFFEL-OTA-1',
                            segments: [
                                new FlightSegmentData(
                                    departureAirport: 'LHR',
                                    arrivalAirport: 'IST',
                                    departureAt: '2026-04-18T08:00:00Z',
                                    arrivalAt: '2026-04-18T13:45:00Z',
                                    marketingCarrier: 'TK',
                                    operatingCarrier: 'TK',
                                    flightNumber: '1980',
                                    cabinClass: 'economy',
                                ),
                                new FlightSegmentData(
                                    departureAirport: 'IST',
                                    arrivalAirport: 'JFK',
                                    departureAt: '2026-04-18T16:15:00Z',
                                    arrivalAt: '2026-04-18T22:30:00Z',
                                    marketingCarrier: 'TK',
                                    operatingCarrier: 'TK',
                                    flightNumber: '111',
                                    cabinClass: 'economy',
                                ),
                            ],
                            price: new PriceBreakdownData(
                                currency: 'USD',
                                baseAmount: 420.00,
                                taxAmount: 60.00,
                                feeAmount: 20.00,
                                totalAmount: 500.00,
                                status: 'available',
                                offerReference: 'DUFFEL-OTA-1',
                            ),
                            cabinSummary: 'ECONOMY',
                        ),
                    ];
                }
            };
        });
    }

    public function test_results_page_renders_ota_style_offer_card_and_detail_panel(): void
    {
        $response = $this
            ->withSession(['display_currency' => 'PKR'])
            ->get(route('frontend.flights.results', [
                'trip_type' => 'one_way',
                'from' => 'London, United Kingdom - Heathrow (LHR)',
                'to' => 'New York, United States - John F. Kennedy International (JFK)',
                'origin' => 'LHR',
                'destination' => 'JFK',
                'departure_date' => '2026-04-18',
                'passengers' => '2',
                'cabin_class' => 'economy',
            ]));

        $response->assertOk();
        $response->assertSee('TK');
        $response->assertSee('London');
        $response->assertSee('1 stop');
        $response->assertSee('LHR');
        $response->assertSee('JFK');
        $response->assertSee('ECONOMY');
        $response->assertSee('DUFFEL');
        $response->assertSee('View details');
        $response->assertSee('Continue to book');
        $response->assertDontSee('Book now');
        $response->assertSeeInOrder(['PKR', '142,035.00'], false);
        $response->assertDontSee('Supplier fare:');
        $response->assertSee('Per traveler');
        $response->assertSee('Marketing carrier:');
        $response->assertSee('Operating carrier:');
        $response->assertSee('Heathrow Airport');
        $response->assertSee('John F. Kennedy International Airport');
        $response->assertSee('Layover:');
        $response->assertDontSee('Route unavailable');
        $response->assertDontSee('Carrier TBA');
        $response->assertDontSee('Airport unavailable');
        $response->assertSee('data-offer-reference="DUFFEL-OTA-1"', false);
    }
}
