<?php

namespace Tests\Feature\Integration;

use App\Integrations\Stub\StubBookingAdapter;
use App\Integrations\Stub\StubFlightPriceAdapter;
use App\Integrations\Travelport\TravelportBookingAdapter;
use App\Integrations\Travelport\TravelportFlightPriceAdapter;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRevalidationGuardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(TravelportFlightPriceAdapter::class, fn () => new StubFlightPriceAdapter('travelport'));
        $this->app->bind(TravelportBookingAdapter::class, fn () => new StubBookingAdapter('travelport'));
        $this->seedAuthorizedIntegrationProvider('travelport');
    }

    public function test_booking_is_blocked_without_fresh_successful_revalidation(): void
    {
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport'],
            'integrations.booking_revalidation_required' => true,
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'booking_success',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'booking-guard-1',
        ])->postJson(route('api.v1.integrations.booking.store'), [
            'offer_reference' => 'offer-missing-revalidation',
            'provider' => 'travelport',
            'travelers' => [
                [
                    'traveler_type' => 'adult',
                    'given_name' => 'Test',
                    'family_name' => 'User',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'fresh_revalidation_required');
    }

    private function seedAuthorizedIntegrationProvider(string $provider): void
    {
        $defaultSlug = (string) config('tenancy.default_slug', 'default');
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => $defaultSlug],
            ['name' => 'Test Tenant', 'plan_tier' => 'enterprise']
        );

        TenantProviderAccess::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'provider' => $provider],
            [
                'is_enabled' => true,
                'can_search' => true,
                'can_price' => true,
                'can_book' => true,
                'allow_multi_provider' => true,
                'allow_fallback' => true,
                'priority_order' => 10,
            ]
        );

        IntegrationConnection::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => $provider,
                'environment' => 'production',
            ],
            [
                'name' => strtoupper($provider).' Test Connection',
                'account_name' => strtoupper($provider).' Account',
                'is_active' => true,
                'status' => 'healthy',
            ]
        );
    }
}
