<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\SupplierBookingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class DuffelBookingIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['duffel']);
    }

    public function test_duffel_booking_requires_fresh_revalidation_snapshot(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.booking_revalidation_required' => true,
            'duffel.booking_live_enabled' => true,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-no-revalidation',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Guard',
                'family_name' => 'Required',
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'fresh_revalidation_required');
    }

    public function test_duffel_order_creation_runs_through_existing_booking_flow(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.booking_revalidation_required' => true,
            'integrations.credential_environment' => 'production',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.offers_path' => '/air/offers',
            'duffel.orders_path' => '/air/orders',
            'duffel.booking_live_enabled' => true,
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
            'https://api.duffel.com/air/orders' => Http::response([
                'data' => [
                    'type' => 'order',
                    'id' => 'ord_001',
                    'status' => 'confirmed',
                    'booking_reference' => 'PNRDUF1',
                    'created_at' => '2026-06-01T10:00:00Z',
                    'total_currency' => 'USD',
                    'total_amount' => '245.00',
                    'base_amount' => '200.00',
                    'tax_amount' => '45.00',
                    'passengers' => [[
                        'id' => 'pas_1',
                        'type' => 'adult',
                        'given_name' => 'Duffel',
                        'family_name' => 'Traveler',
                        'born_on' => '1990-01-01',
                    ]],
                ],
            ], 201),
        ]);

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-pricing-first',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'allow_fallback' => false,
            'correlation_id' => 'duffel-booking-pricing-cid',
        ])->assertOk();

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-create-order',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'correlation_id' => 'duffel-booking-correlation-1',
            'contact_email' => 'duffel@example.com',
            'contact_phone' => '+923001234567',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Duffel',
                'family_name' => 'Traveler',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'PK',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.driver', 'duffel')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.booking_reference', 'ord_001')
            ->assertJsonPath('data.pnr', 'PNRDUF1')
            ->assertJsonPath('data.total_price.total_amount', 245);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.duffel.com/air/orders'
                && data_get($request->data(), 'data.selected_offers.0') === 'off_001'
                && data_get($request->data(), 'data.passengers.0.given_name') === 'Duffel'
                && $request->hasHeader('Authorization')
                && $request->hasHeader('Duffel-Version', 'v2')
                && $request->hasHeader('X-Correlation-ID');
        });

        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'booking_create')
                ->exists()
        );
        $bookingLog = IntegrationRequestLog::query()
            ->where('provider', 'duffel')
            ->where('operation', 'booking_create')
            ->latest('id')
            ->firstOrFail();
        $this->assertNotNull($bookingLog->responseLog);
        $this->assertSame(201, (int) $bookingLog->responseLog->status_code);
        $this->assertGreaterThanOrEqual(0, (int) $bookingLog->responseLog->latency_ms);

        $this->assertTrue(
            SupplierBookingSnapshot::query()
                ->where('provider', 'duffel')
                ->where('booking_reference', 'ord_001')
                ->where('pnr', 'PNRDUF1')
                ->exists()
        );
    }

    public function test_duffel_booking_is_denied_when_connection_is_unhealthy(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.booking_revalidation_required' => false,
            'duffel.booking_live_enabled' => true,
        ]);

        $connection = IntegrationConnection::query()->where('provider', 'duffel')->firstOrFail();
        $connection->forceFill([
            'is_active' => true,
            'status' => 'failed',
        ])->save();

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-connection-failed',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'off_001',
            'provider' => 'duffel',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Health',
                'family_name' => 'Denied',
            ]],
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'integration_provider_unavailable');
    }

    public function test_duffel_failed_booking_request_is_logged_for_monitoring(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.booking_revalidation_required' => true,
            'integrations.credential_environment' => 'production',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.offers_path' => '/air/offers',
            'duffel.orders_path' => '/air/orders',
            'duffel.booking_live_enabled' => true,
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
            'https://api.duffel.com/air/offers/off_fail_001*' => Http::response([
                'data' => [
                    'type' => 'offer',
                    'id' => 'off_fail_001',
                    'total_currency' => 'USD',
                    'total_amount' => '300.00',
                    'base_amount' => '240.00',
                    'tax_amount' => '60.00',
                ],
            ], 200),
            'https://api.duffel.com/air/orders' => Http::response([
                'errors' => [[
                    'title' => 'Supplier booking failed',
                    'detail' => 'Duffel order creation failed.',
                ]],
            ], 502),
        ]);

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-pricing-before-fail',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'off_fail_001',
            'provider' => 'duffel',
            'allow_fallback' => false,
            'correlation_id' => 'duffel-booking-failed-correlation',
        ])->assertOk();

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-booking-create-fail',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'off_fail_001',
            'provider' => 'duffel',
            'correlation_id' => 'duffel-booking-failed-correlation',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Duffel',
                'family_name' => 'Failure',
            ]],
        ]);

        $this->assertContains($response->status(), [502, 503]);
        $this->assertContains((string) data_get($response->json(), 'error.code'), [
            'supplier_booking_failed',
            'supplier_transport_failed',
            'integration_provider_unavailable',
        ]);

        $this->assertFalse(
            SupplierBookingSnapshot::query()
                ->where('provider', 'duffel')
                ->where('booking_reference', 'ord_fail_001')
                ->exists()
        );
        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->whereIn('operation', ['pricing', 'booking_create'])
                ->exists()
        );

        $failedBookingLog = IntegrationRequestLog::query()
            ->where('provider', 'duffel')
            ->where('operation', 'booking_create')
            ->latest('id')
            ->first();
        if ($failedBookingLog !== null) {
            $this->assertNotNull($failedBookingLog->responseLog);
            $this->assertGreaterThanOrEqual(400, (int) $failedBookingLog->responseLog->status_code);
            $this->assertGreaterThanOrEqual(0, (int) $failedBookingLog->responseLog->latency_ms);
        }
    }
}
