<?php

namespace Tests\Feature\Frontend;

use App\Models\IntegrationConnection;
use App\Models\SupplierSearchSession;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendFlightSearchTest extends TestCase
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
            'provider' => 'stub',
            'can_search' => true,
            'can_price' => false,
            'can_book' => false,
            'allow_multi_provider' => false,
            'allow_fallback' => false,
            'priority_order' => 1,
            'is_enabled' => true,
        ]);

        IntegrationConnection::query()->create([
            'name' => 'Stub Search Connection',
            'provider' => 'stub',
            'environment' => 'production',
            'base_url' => 'https://stub.example.test',
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_home_hero_form_targets_public_flight_results_route(): void
    {
        $response = $this->get(route('frontend.home'));

        $response->assertOk();
        $response->assertSee('action="'.route('frontend.flights.results').'"', false);
        $response->assertSee('name="origin"', false);
        $response->assertSee('name="destination"', false);
    }

    public function test_public_flight_results_page_shows_matrix_denial_when_provider_is_not_runtime_allowed(): void
    {
        config()->set('integrations.driver', 'stub');
        config()->set('integrations.supported_drivers', ['stub', 'duffel']);
        config()->set('integrations.stub_scenario', 'flight_search_success');

        $response = $this->get(route('frontend.flights.results', [
            'trip_type' => 'one_way',
            'from' => 'LHE',
            'to' => 'DXB',
            'provider' => 'duffel',
            'departure_date' => now()->addDays(10)->toDateString(),
            'passengers' => '2',
            'cabin_class' => 'economy',
        ]));

        $response->assertOk();
        $response->assertSee('Flight search is unavailable');
        $response->assertSee('Provider is not allowed for this tenant.');
        $this->assertFalse(
            SupplierSearchSession::query()->where('provider', 'stub')->where('status', 'completed')->exists(),
            'Did not expect supplier snapshot when provider authorization is denied.'
        );
    }

    public function test_public_flight_results_accepts_display_labels_but_submits_iata_codes(): void
    {
        config()->set('integrations.driver', 'stub');
        config()->set('integrations.supported_drivers', ['stub']);
        config()->set('integrations.stub_scenario', 'flight_search_success');

        $response = $this->get(route('frontend.flights.results', [
            'trip_type' => 'one_way',
            'from' => 'Lahore, Pakistan - Allama Iqbal International (LHE)',
            'to' => 'Dubai, United Arab Emirates - Dubai International (DXB)',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_date' => now()->addDays(10)->toDateString(),
            'passengers' => '2',
            'cabin_class' => 'economy',
        ]));

        $response->assertOk();
        $response->assertDontSee('Please select a valid departure airport.');
    }
}
