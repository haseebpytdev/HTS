<?php

namespace Tests\Feature\Frontend;

use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\Duffel\DuffelFlightSearchAdapter;
use App\Models\Currency;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FlightResultsFilteringTest extends TestCase
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
            ['iata' => 'DXB', 'airport' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'United Arab Emirates'],
            ['iata' => 'KHI', 'airport' => 'Jinnah International Airport', 'city' => 'Karachi', 'country' => 'Pakistan'],
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
                            id: 'offer-a',
                            providerOfferReference: 'OFFER-A',
                            segments: [
                                new FlightSegmentData('LHR', 'DXB', '2026-04-18T06:00:00Z', '2026-04-18T10:00:00Z', 'PK', 'PK', '301', 'economy'),
                                new FlightSegmentData('DXB', 'KHI', '2026-04-18T11:00:00Z', '2026-04-18T14:00:00Z', 'PK', 'PK', '302', 'economy'),
                            ],
                            price: new PriceBreakdownData('USD', 250, 40, 10, 300, 'available', 'OFFER-A'),
                            cabinSummary: 'ECONOMY',
                        ),
                        new FlightOfferData(
                            id: 'offer-b',
                            providerOfferReference: 'OFFER-B',
                            segments: [
                                new FlightSegmentData('LHR', 'KHI', '2026-04-18T09:00:00Z', '2026-04-18T12:00:00Z', 'EK', 'EK', '501', 'business'),
                            ],
                            price: new PriceBreakdownData('USD', 420, 60, 20, 500, 'available', 'OFFER-B'),
                            cabinSummary: 'BUSINESS',
                        ),
                        new FlightOfferData(
                            id: 'offer-c',
                            providerOfferReference: 'OFFER-C',
                            segments: [
                                new FlightSegmentData('LHR', 'KHI', '2026-04-18T04:30:00Z', '2026-04-18T10:00:00Z', 'PK', 'PK', '401', 'economy'),
                            ],
                            price: new PriceBreakdownData('USD', 330, 55, 15, 400, 'available', 'OFFER-C'),
                            cabinSummary: 'ECONOMY',
                        ),
                    ];
                }
            };
        });
    }

    public function test_results_support_sorting_by_cheapest_fastest_and_earliest_departure(): void
    {
        $base = $this->baseQuery();

        $cheapest = $this->withSession(['display_currency' => 'USD'])
            ->get(route('frontend.flights.results', array_merge($base, ['sort' => 'cheapest'])));
        $cheapest->assertOk();
        $cheapest->assertSee('option value="cheapest" selected', false);
        $cheapest->assertSee('data-offer-reference="OFFER-A"', false);
        $cheapest->assertSee('data-offer-reference="OFFER-B"', false);
        $cheapest->assertSee('data-offer-reference="OFFER-C"', false);

        $fastest = $this->withSession(['display_currency' => 'USD'])
            ->get(route('frontend.flights.results', array_merge($base, ['sort' => 'fastest'])));
        $fastest->assertOk();
        $fastest->assertSee('option value="fastest" selected', false);
        $fastest->assertSee('data-offer-reference="OFFER-A"', false);
        $fastest->assertSee('data-offer-reference="OFFER-B"', false);
        $fastest->assertSee('data-offer-reference="OFFER-C"', false);

        $earliest = $this->withSession(['display_currency' => 'USD'])
            ->get(route('frontend.flights.results', array_merge($base, ['sort' => 'earliest_departure'])));
        $earliest->assertOk();
        $earliest->assertSee('option value="earliest_departure" selected', false);
        $earliest->assertSee('data-offer-reference="OFFER-A"', false);
        $earliest->assertSee('data-offer-reference="OFFER-B"', false);
        $earliest->assertSee('data-offer-reference="OFFER-C"', false);
    }

    public function test_results_support_filtering_by_stops_airline_departure_window_and_price_range(): void
    {
        $response = $this->withSession(['display_currency' => 'USD'])
            ->get(route('frontend.flights.results', array_merge($this->baseQuery(), [
                'stops' => '0',
                'airline' => 'PK',
                'departure_window' => 'night',
                'price_min' => '350',
                'price_max' => '450',
            ])));

        $response->assertOk();
        $response->assertSee('data-offer-reference="OFFER-C"', false);
        $response->assertDontSee('data-offer-reference="OFFER-A"', false);
        $response->assertDontSee('data-offer-reference="OFFER-B"', false);
    }

    /**
     * @return array<string, string>
     */
    private function baseQuery(): array
    {
        return [
            'trip_type' => 'one_way',
            'from' => 'London, United Kingdom - Heathrow (LHR)',
            'to' => 'Karachi, Pakistan - Jinnah International (KHI)',
            'origin' => 'LHR',
            'destination' => 'KHI',
            'departure_date' => '2026-04-18',
            'passengers' => '2',
            'cabin_class' => 'economy',
        ];
    }
}
