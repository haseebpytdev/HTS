<?php

namespace Tests\Feature\Frontend;

use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Duffel\DuffelFlightSearchAdapter;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use App\Repositories\IntegrationConnectionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendFlightSearchProviderResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_search_uses_platform_owned_duffel_sandbox_connection_when_tenant_owned_connection_is_missing(): void
    {
        $defaultTenant = Tenant::query()->create([
            'name' => 'Default Tenant',
            'slug' => 'default',
            'plan_tier' => 'enterprise',
        ]);
        $platformOwnerTenant = Tenant::query()->create([
            'name' => 'Platform Owner Tenant',
            'slug' => 'platform-owner',
            'plan_tier' => 'enterprise',
        ]);

        TenantProviderAccess::query()->create([
            'tenant_id' => $defaultTenant->id,
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'can_search' => true,
            'can_price' => false,
            'can_book' => false,
            'allow_multi_provider' => false,
            'allow_fallback' => false,
            'priority_order' => 10,
            'is_enabled' => true,
        ]);

        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Global Duffel Sandbox',
            'name' => 'Global Duffel Sandbox',
            'tenant_id' => $platformOwnerTenant->id,
            'ownership_type' => 'platform_owner',
            'ownership_tenant_id' => null,
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
            'base_url' => 'https://api.duffel.com',
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
                    return [];
                }
            };
        });

        $response = $this->get(route('frontend.flights.results', [
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
        $response->assertDontSee('No active supplier connection found for the required environment.');
        $response->assertSee('Flight results');
        $response->assertSee('0 offer(s) found.');
    }

    public function test_connection_resolver_prefers_default_platform_connection_when_multiple_are_usable(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Resolver Tenant',
            'slug' => 'resolver-tenant',
            'plan_tier' => 'enterprise',
        ]);

        $nonDefault = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Platform Sandbox Non Default',
            'name' => 'Platform Sandbox Non Default',
            'tenant_id' => null,
            'ownership_type' => 'platform_owner',
            'is_active' => true,
            'is_default' => false,
            'status' => 'healthy',
        ]);
        $default = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Platform Sandbox Default',
            'name' => 'Platform Sandbox Default',
            'tenant_id' => null,
            'ownership_type' => 'platform_owner',
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
        ]);

        $resolved = app(IntegrationConnectionRepository::class)
            ->findActiveForTenantProviderAndEnvironment('duffel', 'sandbox', $tenant->id);

        $this->assertNotNull($resolved);
        $this->assertSame($default->id, $resolved?->id);
        $this->assertNotSame($nonDefault->id, $resolved?->id);
    }

    public function test_connection_resolver_uses_highest_runtime_priority_when_no_default_exists(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Resolver Priority Tenant',
            'slug' => 'resolver-priority-tenant',
            'plan_tier' => 'enterprise',
        ]);

        $connected = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Connected Candidate',
            'name' => 'Connected Candidate',
            'tenant_id' => null,
            'ownership_type' => 'platform_owner',
            'is_active' => true,
            'is_default' => false,
            'status' => 'connected',
            'last_success_at' => now()->subMinutes(2),
            'last_tested_at' => now()->subMinutes(2),
        ]);
        $healthy = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Healthy Candidate',
            'name' => 'Healthy Candidate',
            'tenant_id' => null,
            'ownership_type' => 'platform_owner',
            'is_active' => true,
            'is_default' => false,
            'status' => 'healthy',
            'last_success_at' => now()->subMinutes(5),
            'last_tested_at' => now()->subMinutes(5),
        ]);

        $resolved = app(IntegrationConnectionRepository::class)
            ->findActiveForTenantProviderAndEnvironment('duffel', 'sandbox', $tenant->id);

        $this->assertNotNull($resolved);
        $this->assertSame($healthy->id, $resolved?->id);
        $this->assertNotSame($connected->id, $resolved?->id);
    }
}
