<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\ServiceModule;
use App\Services\Integrations\BookingRevalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class DuffelFlightPricingIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['duffel']);
    }

    public function test_duffel_offer_revalidation_runs_through_pricing_flow_and_records_snapshots(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'production',
            'integrations.booking_revalidation_required' => true,
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.offers_path' => '/air/offers',
            'duffel.credentials.production.client_id' => 'duffel',
            'duffel.credentials.production.client_secret' => 'duffel_test_integration_token',
        ]);

        $connection = IntegrationConnection::query()->where('provider', 'duffel')->firstOrFail();
        $connection->forceFill([
            'environment' => 'production',
            'is_active' => true,
            'status' => 'healthy',
        ])->save();

        Http::fake([
            'https://api.duffel.com/air/offers/off_001*' => Http::response([
                'data' => [
                    'type' => 'offer',
                    'id' => 'off_001',
                    'total_currency' => 'USD',
                    'total_amount' => '245.00',
                    'base_amount' => '200.00',
                    'tax_amount' => '45.00',
                ],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-pricing-1',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'allow_fallback' => false,
            'correlation_id' => 'duffel-pricing-correlation-1',
            'opaque_context' => [
                'selected_offer' => [
                    'id' => 'off_001',
                    'provider_offer_reference' => 'off_001',
                    'passengers' => [
                        ['id' => 'pas_001'],
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.driver', 'duffel')
            ->assertJsonPath('data.correlation_id', 'duffel-pricing-correlation-1')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.total_amount', 245);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://api.duffel.com/air/offers/off_001')
                && str_contains($request->url(), 'selected_passengers%5B%5D%5B0%5D=pas_001')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('Duffel-Version', 'v2')
                && $request->hasHeader('X-Correlation-ID');
        });

        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'pricing')
                ->exists()
        );
        $pricingLog = IntegrationRequestLog::query()
            ->where('provider', 'duffel')
            ->where('operation', 'pricing')
            ->latest('id')
            ->firstOrFail();
        $this->assertNotNull($pricingLog->responseLog);
        $this->assertSame(200, (int) $pricingLog->responseLog->status_code);
        $this->assertGreaterThanOrEqual(0, (int) $pricingLog->responseLog->latency_ms);

        /** @var BookingRevalidationGuard $guard */
        $guard = app(BookingRevalidationGuard::class);
        $snapshot = $guard->snapshot('off_001', 'duffel');
        $this->assertSame('duffel-pricing-correlation-1', $snapshot['correlation_id'] ?? null);
        $this->assertSame(245.0, (float) ($snapshot['total_amount'] ?? 0));
        $this->assertSame('USD', $snapshot['currency'] ?? null);

        $booking = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-after-pricing-1',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Duffel',
                'family_name' => 'Tester',
            ]],
        ]);

        $booking->assertCreated()
            ->assertJsonPath('data.driver', 'duffel')
            ->assertJsonPath('data.status', 'not_implemented');
    }

    public function test_duffel_pricing_is_denied_when_module_operation_is_disabled(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'production',
        ]);

        ServiceModule::query()->create([
            'module_key' => 'duffel_flights',
            'code' => 'duffel_flights',
            'name' => 'Duffel Flights',
            'provider' => 'duffel',
            'provider_name' => 'Duffel',
            'service_type' => 'Flights',
            'environment' => 'production',
            'is_active' => true,
            'is_default' => true,
            'status' => 'active',
            'connection_status' => 'connected',
            'supported_operations_json' => ['search', 'booking'],
            'sort_order' => 10,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-pricing-module-disabled',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'allow_fallback' => false,
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'integration_provider_unavailable');
    }
}

