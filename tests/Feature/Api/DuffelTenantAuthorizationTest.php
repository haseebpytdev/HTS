<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DuffelTenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'integrations.api_key' => 'test-key',
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel', 'travelport', 'sabre', 'amadeus'],
            'integrations.credential_environment' => 'production',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.booking_live_enabled' => false,
        ]);

        $this->ensureTenantProviderQuotaColumns();
    }

    public function test_blocks_duffel_when_operation_permission_is_disabled_for_tenant(): void
    {
        [$tenant] = $this->seedDuffelTenantContext([
            'can_search' => false,
            'can_price' => true,
            'can_book' => true,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-auth-op-search',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'integration_access_denied');
    }

    public function test_blocks_duffel_when_fallback_not_allowed_by_tenant_policy(): void
    {
        [$tenant] = $this->seedDuffelTenantContext([
            'can_search' => true,
            'allow_fallback' => false,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-auth-no-fallback',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'providers' => ['duffel'],
            'allow_fallback' => true,
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'integration_access_denied');
    }

    public function test_blocks_duffel_when_connection_is_not_healthy_or_active(): void
    {
        [$tenant, $connection] = $this->seedDuffelTenantContext();
        $connection->forceFill([
            'is_active' => true,
            'status' => 'failed',
        ])->save();

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-auth-unhealthy-connection',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'offer_reference' => 'off_001',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'integration_provider_unavailable');
    }

    public function test_blocks_duffel_search_when_daily_quota_hard_limit_is_exceeded(): void
    {
        [$tenant, $connection] = $this->seedDuffelTenantContext([
            'usage_quota_json' => ['bookings_monthly' => 0, 'searches_daily' => 1],
            'soft_limit_percent' => 80,
            'hard_limit_enforced' => true,
            'overage_alert_enabled' => true,
        ]);

        IntegrationRequestLog::query()->create([
            'integration_connection_id' => $connection->id,
            'provider' => 'duffel',
            'operation' => 'search_offer_request',
            'environment' => 'production',
            'correlation_id' => 'duffel-quota-search-1',
            'http_method' => 'POST',
            'url' => 'https://api.duffel.com/air/offer_requests',
            'request_headers' => ['X-Correlation-ID' => 'duffel-quota-search-1'],
            'request_body' => ['data' => ['type' => 'offer_request']],
            'created_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-auth-search-quota-hit',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'integration_access_denied');
    }

    public function test_blocks_duffel_booking_when_monthly_quota_hard_limit_is_exceeded(): void
    {
        [$tenant, $connection] = $this->seedDuffelTenantContext([
            'can_search' => true,
            'can_price' => true,
            'can_book' => true,
            'usage_quota_json' => ['bookings_monthly' => 1, 'searches_daily' => 0],
            'soft_limit_percent' => 80,
            'hard_limit_enforced' => true,
            'overage_alert_enabled' => true,
        ]);

        IntegrationRequestLog::query()->create([
            'integration_connection_id' => $connection->id,
            'provider' => 'duffel',
            'operation' => 'booking_create',
            'environment' => 'production',
            'correlation_id' => 'duffel-quota-booking-1',
            'http_method' => 'POST',
            'url' => 'https://api.duffel.com/air/orders',
            'request_headers' => ['X-Correlation-ID' => 'duffel-quota-booking-1'],
            'request_body' => ['data' => ['type' => 'order']],
            'created_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-auth-booking-quota-hit',
        ])->postJson('/api/v1/integrations/booking', [
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'offer_reference' => 'off_quota_001',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Quota',
                'family_name' => 'Blocked',
            ]],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'integration_access_denied');
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array{0: Tenant, 1: IntegrationConnection}
     */
    private function seedDuffelTenantContext(array $override = []): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Duffel Tenant '.uniqid(),
            'slug' => 'duffel-tenant-'.uniqid(),
            'plan_tier' => 'enterprise',
        ]);

        $access = array_merge([
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'plan_code' => 'enterprise',
            'is_enabled' => true,
            'can_search' => true,
            'can_price' => true,
            'can_book' => true,
            'allow_multi_provider' => true,
            'allow_fallback' => true,
            'priority_order' => 10,
            'usage_quota_json' => ['bookings_monthly' => 0, 'searches_daily' => 0],
            'soft_limit_percent' => 80,
            'hard_limit_enforced' => false,
            'overage_alert_enabled' => true,
        ], $override);
        TenantProviderAccess::query()->create($access);

        $connection = IntegrationConnection::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'environment' => 'production',
            'account_name' => 'Duffel Tenant Connection',
            'name' => 'Duffel Tenant Connection',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        return [$tenant, $connection];
    }

    private function ensureTenantProviderQuotaColumns(): void
    {
        if (! Schema::hasColumn('tenant_provider_access', 'usage_quota_json')
            || ! Schema::hasColumn('tenant_provider_access', 'soft_limit_percent')
            || ! Schema::hasColumn('tenant_provider_access', 'hard_limit_enforced')
            || ! Schema::hasColumn('tenant_provider_access', 'overage_alert_enabled')
        ) {
            Schema::table('tenant_provider_access', function (Blueprint $table): void {
                if (! Schema::hasColumn('tenant_provider_access', 'usage_quota_json')) {
                    $table->json('usage_quota_json')->nullable();
                }
                if (! Schema::hasColumn('tenant_provider_access', 'soft_limit_percent')) {
                    $table->unsignedTinyInteger('soft_limit_percent')->default(80);
                }
                if (! Schema::hasColumn('tenant_provider_access', 'hard_limit_enforced')) {
                    $table->boolean('hard_limit_enforced')->default(false);
                }
                if (! Schema::hasColumn('tenant_provider_access', 'overage_alert_enabled')) {
                    $table->boolean('overage_alert_enabled')->default(true);
                }
            });
        }
    }
}
