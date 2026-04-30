<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\Traveler;
use App\Services\Booking\BookingSupplierIntegrationBridge;
use App\Services\Integrations\BookingRevalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuffelBookingBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bridge_dispatches_duffel_booking_and_persists_supplier_references_locally(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.booking_revalidation_required' => true,
            'integrations.credential_environment' => 'production',
            'duffel.booking_live_enabled' => true,
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.orders_path' => '/air/orders',
            'duffel.version' => 'v2',
            'duffel.credentials.production.client_id' => 'duffel',
            'duffel.credentials.production.client_secret' => 'duffel_test_integration_token',
        ]);
        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'tenant_id' => null,
            'environment' => 'production',
            'name' => 'Duffel Bridge Connection',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $booking = Booking::query()->create([
            'quotation_id' => 1,
            'agency_id' => 1,
            'booking_number' => 'BK-DF6-001',
            'status' => BookingStatus::Confirmed->value,
            'total_amount' => 245.00,
            'currency' => 'USD',
            'payment_status' => 'unpaid',
            'customer_email' => 'bridge@example.com',
            'customer_phone' => '+92300111222',
            'internal_notes' => (string) json_encode([
                'supplier_booking_request' => [
                    'provider' => 'duffel',
                    'offer_reference' => 'off_001',
                    'correlation_id' => 'bridge-duffel-correlation-1',
                ],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $booking->travelers()->create([
            'first_name' => 'Bridge',
            'last_name' => 'Traveler',
            'traveler_type' => Traveler::TYPE_ADULT,
            'nationality' => 'PK',
        ]);

        /** @var BookingRevalidationGuard $guard */
        $guard = app(BookingRevalidationGuard::class);
        $guard->recordRevalidationResult(
            offerReference: 'off_001',
            driver: 'duffel',
            status: 'confirmed',
            correlationId: 'bridge-revalidation-correlation-1',
            totalAmount: 245.0,
            currency: 'USD',
        );
        Http::fake([
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
                        'type' => 'adult',
                        'given_name' => 'Bridge',
                        'family_name' => 'Traveler',
                        'born_on' => '1990-01-01',
                    ]],
                ],
            ], 201),
        ]);

        /** @var BookingSupplierIntegrationBridge $bridge */
        $bridge = app(BookingSupplierIntegrationBridge::class);
        $bridge->runPostConfirmHooks($booking->fresh());

        $booking->refresh();
        $this->assertSame(BookingSupplierIntegrationBridge::HOOK_CONFIRMED, $booking->supplier_flight_hook_status);
        $this->assertSame(BookingSupplierIntegrationBridge::HOOK_QUEUED, $booking->supplier_hotel_hook_status);
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame('duffel', data_get($notes, 'supplier_booking.provider'));
        $this->assertSame('off_001', data_get($notes, 'supplier_booking.offer_reference'));
        $this->assertSame('ord_001', data_get($notes, 'supplier_booking.booking_reference'));
        $this->assertSame('PNRDUF1', data_get($notes, 'supplier_booking.pnr'));
        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'booking_create')
                ->exists()
        );
    }

    public function test_bridge_marks_failed_when_supplier_offer_reference_is_missing(): void
    {
        $booking = Booking::query()->create([
            'quotation_id' => 1,
            'agency_id' => 1,
            'booking_number' => 'BK-DF6-002',
            'status' => BookingStatus::Confirmed->value,
            'total_amount' => 100.00,
            'currency' => 'USD',
            'payment_status' => 'unpaid',
            'internal_notes' => (string) json_encode([
                'supplier_booking_request' => [
                    'provider' => 'duffel',
                ],
            ], JSON_UNESCAPED_SLASHES),
        ]);

        /** @var BookingSupplierIntegrationBridge $bridge */
        $bridge = app(BookingSupplierIntegrationBridge::class);
        $bridge->dispatchFlightBookingPlaceholder($booking->fresh());

        $booking->refresh();
        $this->assertSame(BookingSupplierIntegrationBridge::HOOK_FAILED, $booking->supplier_flight_hook_status);
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame('Missing supplier offer reference for flight booking bridge.', data_get($notes, 'supplier_booking_error'));
    }
}
