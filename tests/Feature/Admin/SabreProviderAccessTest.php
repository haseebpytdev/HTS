<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use App\Models\User;
use App\Services\Integrations\TenantProviderAuthorizationService;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreProviderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_updates_sabre_as_search_only_even_if_pricing_booking_submitted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'plan_tier' => 'enterprise',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.integrations.access-matrix.update', $tenant), [
            'rows' => [[
                'provider' => 'sabre',
                'environment' => 'sandbox',
                'plan_code' => 'enterprise',
                'is_enabled' => 1,
                'can_search' => 1,
                'can_price' => 1,
                'can_book' => 1,
                'allow_multi_provider' => 1,
                'allow_fallback' => 1,
                'priority_order' => 20,
            ]],
        ]);

        $response->assertRedirect(route('admin.integrations.access-matrix.index'));

        $this->assertDatabaseHas('tenant_provider_access', [
            'tenant_id' => $tenant->id,
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'is_enabled' => 1,
            'can_search' => 1,
            'can_price' => 0,
            'can_book' => 0,
        ]);
    }

    public function test_tenant_one_can_search_with_sabre_only_after_access_is_enabled(): void
    {
        config(['integrations.credential_environment' => 'test']);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'plan_tier' => 'enterprise',
        ]);

        $connection = IntegrationConnection::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'name' => 'Sabre Sandbox Connection',
            'account_name' => 'Sabre Sandbox',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'healthy',
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'username',
            'credential_value_encrypted' => 'sabre_user',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'password',
            'credential_value_encrypted' => 'sabre_password',
            'is_secret' => true,
        ]);
        /** @var TenantProviderAuthorizationService $authz */
        $authz = app(TenantProviderAuthorizationService::class);

        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'is_enabled' => false,
            'can_search' => false,
            'can_price' => false,
            'can_book' => false,
            'allow_multi_provider' => true,
            'allow_fallback' => true,
            'priority_order' => 10,
        ]);

        try {
            $authz->authorizeProvider(
                tenantId: $tenant->id,
                agencyId: null,
                provider: 'sabre',
                operation: 'search'
            );
            $this->fail('Expected disabled tenant-provider mapping to deny search.');
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('integration_access_denied', $e->normalizedCode);
        }

        TenantProviderAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'sabre')
            ->update([
                'is_enabled' => true,
                'can_search' => true,
                'can_price' => false,
                'can_book' => false,
                'allow_multi_provider' => false,
                'allow_fallback' => false,
            ]);

        $authz->authorizeProvider(
            tenantId: $tenant->id,
            agencyId: null,
            provider: 'sabre',
            operation: 'search'
        );
    }
}
