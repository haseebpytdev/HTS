<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class BookingRevalidationGuardTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['travelport', 'sabre', 'amadeus']);
    }

    public function test_booking_is_blocked_without_fresh_successful_revalidation(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport'],
            'integrations.booking_revalidation_required' => true,
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'booking_success',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'booking-guard-api-1',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'TP-REF-001',
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

    public function test_booking_succeeds_after_fresh_revalidation_snapshot_exists(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport'],
            'integrations.booking_revalidation_required' => true,
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_pricing_success',
        ]);

        $pricing = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'booking-guard-api-2-pricing',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'TP-REF-001',
            'provider' => 'travelport',
            'providers' => ['travelport'],
            'allow_fallback' => false,
            'opaque_context' => ['scenario' => 'flight_pricing_success'],
        ]);

        $pricing->assertOk()
            ->assertJsonPath('data.status', 'available');

        config(['integrations.stub_scenario' => 'booking_success']);

        $booking = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'booking-guard-api-2-booking',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'TP-REF-001',
            'provider' => 'travelport',
            'travelers' => [
                [
                    'traveler_type' => 'adult',
                    'given_name' => 'Test',
                    'family_name' => 'User',
                ],
            ],
        ]);

        $booking->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.driver', 'travelport');
    }

    public function test_booking_fails_when_revalidation_snapshot_exists_for_different_provider(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre', 'travelport'],
            'integrations.booking_revalidation_required' => true,
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_pricing_success',
        ]);

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'booking-guard-provider-mismatch-pricing',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'MISMATCH-REF-001',
            'provider' => 'sabre',
            'providers' => ['sabre', 'travelport'],
            'allow_fallback' => false,
            'opaque_context' => ['scenario' => 'flight_pricing_success'],
        ])->assertOk();

        config(['integrations.stub_scenario' => 'booking_success']);
        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'booking-guard-provider-mismatch-book',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'MISMATCH-REF-001',
            'provider' => 'travelport',
            'travelers' => [[
                'traveler_type' => 'adult',
                'given_name' => 'Mismatch',
                'family_name' => 'Provider',
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'fresh_revalidation_required');
    }
}
