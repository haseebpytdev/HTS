<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProviderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'integrations.api_key' => 'test-key',
            'integrations.driver' => 'stub',
            'integrations.supported_drivers' => ['travelport', 'sabre', 'amadeus', 'stub'],
            'integrations.credential_environment' => 'production',
            'integrations.stub_scenario' => 'flight_search_success',
        ]);
    }

    public function test_blocks_provider_when_tenant_access_disallows_provider(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'plan_tier' => 'pro',
        ]);

        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'is_enabled' => false,
            'can_search' => false,
            'can_price' => false,
            'can_book' => false,
            'priority_order' => 10,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'tenant-auth-1',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'integration_access_denied');
    }

    public function test_blocks_provider_when_connection_is_not_healthy_active(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'plan_tier' => 'enterprise',
        ]);

        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'is_enabled' => true,
            'can_search' => true,
            'can_price' => true,
            'can_book' => true,
            'allow_multi_provider' => true,
            'allow_fallback' => true,
            'priority_order' => 10,
        ]);

        IntegrationConnection::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'environment' => 'production',
            'account_name' => 'Tenant B Travelport',
            'name' => 'Tenant B Travelport',
            'is_active' => true,
            'status' => 'failed',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'tenant-auth-2',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'integration_provider_unavailable');
    }

    public function test_allows_provider_when_access_and_connection_are_valid(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant C',
            'slug' => 'tenant-c',
            'plan_tier' => 'enterprise',
        ]);

        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'is_enabled' => true,
            'can_search' => true,
            'can_price' => true,
            'can_book' => true,
            'allow_multi_provider' => true,
            'allow_fallback' => true,
            'priority_order' => 10,
        ]);

        IntegrationConnection::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'environment' => 'production',
            'account_name' => 'Tenant C Travelport',
            'name' => 'Tenant C Travelport',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'tenant-auth-3',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['offers']]);
    }
}
