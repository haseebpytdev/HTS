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

class DuffelHoldOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bridge_settles_duffel_hold_order_payment_after_repricing(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.booking_revalidation_required' => true,
            'integrations.credential_environment' => 'production',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.offers_path' => '/air/offers',
            'duffel.orders_path' => '/air/orders',
            'duffel.booking_live_enabled' => true,
            'duffel.hold_order_payment_enabled' => true,
            'duffel.credentials.production.client_id' => 'duffel',
            'duffel.credentials.production.client_secret' => 'duffel_test_integration_token',
        ]);
        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'tenant_id' => null,
            'environment' => 'production',
            'name' => 'Duffel Hold Payment Connection',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $booking = Booking::query()->create([
            'quotation_id' => 1,
            'agency_id' => 1,
            'booking_number' => 'BK-DF7-001',
            'status' => BookingStatus::Confirmed->value,
            'total_amount' => 315.00,
            'currency' => 'USD',
            'payment_status' => 'unpaid',
            'customer_email' => 'hold@example.com',
            'customer_phone' => '+923001111111',
            'internal_notes' => (string) json_encode([
                'supplier_booking_request' => [
                    'provider' => 'duffel',
                    'offer_reference' => 'off_hold_001',
                    'correlation_id' => 'duffel-hold-correlation-1',
                ],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $booking->travelers()->create([
            'first_name' => 'Hold',
            'last_name' => 'Traveler',
            'traveler_type' => Traveler::TYPE_ADULT,
            'date_of_birth' => '1990-01-01',
            'nationality' => 'PK',
        ]);

        /** @var BookingRevalidationGuard $guard */
        $guard = app(BookingRevalidationGuard::class);
        $guard->recordRevalidationResult(
            offerReference: 'off_hold_001',
            driver: 'duffel',
            status: 'confirmed',
            correlationId: 'duffel-hold-revalidation-1',
            totalAmount: 315.0,
            currency: 'USD',
        );

        Http::fake([
            'https://api.duffel.com/air/orders' => Http::response([
                'data' => [
                    'type' => 'order',
                    'id' => 'ord_hold_001',
                    'status' => 'awaiting_payment',
                    'booking_reference' => 'PNRHOLD1',
                    'total_currency' => 'USD',
                    'total_amount' => '300.00',
                    'base_amount' => '260.00',
                    'tax_amount' => '40.00',
                    'passengers' => [[
                        'type' => 'adult',
                        'given_name' => 'Hold',
                        'family_name' => 'Traveler',
                    ]],
                ],
            ], 201),
            'https://api.duffel.com/air/orders/ord_hold_001' => Http::response([
                'data' => [
                    'type' => 'order',
                    'id' => 'ord_hold_001',
                    'status' => 'awaiting_payment',
                    'payment_status' => 'awaiting_payment',
                    'booking_reference' => 'PNRHOLD1',
                    'total_currency' => 'USD',
                    'total_amount' => '315.00',
                    'base_amount' => '270.00',
                    'tax_amount' => '45.00',
                ],
            ], 200),
            'https://api.duffel.com/air/payments' => Http::response([
                'data' => [
                    'type' => 'payment',
                    'id' => 'pay_001',
                    'status' => 'succeeded',
                ],
            ], 201),
        ]);

        /** @var BookingSupplierIntegrationBridge $bridge */
        $bridge = app(BookingSupplierIntegrationBridge::class);
        $bridge->dispatchFlightBookingPlaceholder($booking->fresh());

        $booking->refresh();
        $this->assertSame(BookingSupplierIntegrationBridge::HOOK_CONFIRMED, $booking->supplier_flight_hook_status);
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame('ord_hold_001', data_get($notes, 'supplier_booking.booking_reference'));
        $this->assertSame('pending', data_get($notes, 'supplier_booking.status'));
        $this->assertTrue((bool) data_get($notes, 'supplier_payment.requires_payment'));
        $this->assertSame('succeeded', data_get($notes, 'supplier_payment.status'));
        $this->assertSame('pay_001', data_get($notes, 'supplier_payment.payment_reference'));
        $this->assertSame(315.0, (float) data_get($notes, 'supplier_payment.repriced_total_amount'));
        $this->assertSame('USD', data_get($notes, 'supplier_payment.repriced_currency'));
        $this->assertSame('not_recorded_supplier_side_only', data_get($notes, 'supplier_payment.local_payment_record'));

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.duffel.com/air/payments'
                && data_get($request->data(), 'data.order_id') === 'ord_hold_001'
                && data_get($request->data(), 'data.amount') === '315.00';
        });
        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'booking_payment_reprice')
                ->exists()
        );
        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'booking_payment')
                ->exists()
        );
    }

    public function test_bridge_skips_supplier_payment_when_order_is_not_hold_payment_type(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.booking_revalidation_required' => true,
            'integrations.credential_environment' => 'production',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.orders_path' => '/air/orders',
            'duffel.booking_live_enabled' => true,
            'duffel.hold_order_payment_enabled' => true,
            'duffel.credentials.production.client_id' => 'duffel',
            'duffel.credentials.production.client_secret' => 'duffel_test_integration_token',
        ]);
        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'tenant_id' => null,
            'environment' => 'production',
            'name' => 'Duffel Hold Payment Skip',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $booking = Booking::query()->create([
            'quotation_id' => 1,
            'agency_id' => 1,
            'booking_number' => 'BK-DF7-002',
            'status' => BookingStatus::Confirmed->value,
            'total_amount' => 220.00,
            'currency' => 'USD',
            'payment_status' => 'unpaid',
            'customer_email' => 'skip@example.com',
            'customer_phone' => '+923002222222',
            'internal_notes' => (string) json_encode([
                'supplier_booking_request' => [
                    'provider' => 'duffel',
                    'offer_reference' => 'off_no_hold_001',
                    'correlation_id' => 'duffel-hold-correlation-2',
                ],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $booking->travelers()->create([
            'first_name' => 'No',
            'last_name' => 'Hold',
            'traveler_type' => Traveler::TYPE_ADULT,
        ]);

        /** @var BookingRevalidationGuard $guard */
        $guard = app(BookingRevalidationGuard::class);
        $guard->recordRevalidationResult(
            offerReference: 'off_no_hold_001',
            driver: 'duffel',
            status: 'confirmed',
            correlationId: 'duffel-no-hold-revalidation-1',
            totalAmount: 220.0,
            currency: 'USD',
        );

        Http::fake([
            'https://api.duffel.com/air/orders' => Http::response([
                'data' => [
                    'type' => 'order',
                    'id' => 'ord_no_hold_001',
                    'status' => 'confirmed',
                    'booking_reference' => 'PNRNH1',
                    'total_currency' => 'USD',
                    'total_amount' => '220.00',
                    'base_amount' => '180.00',
                    'tax_amount' => '40.00',
                    'passengers' => [[
                        'type' => 'adult',
                        'given_name' => 'No',
                        'family_name' => 'Hold',
                    ]],
                ],
            ], 201),
            'https://api.duffel.com/air/orders/ord_no_hold_001' => Http::response([], 200),
            'https://api.duffel.com/air/payments' => Http::response([], 201),
        ]);

        /** @var BookingSupplierIntegrationBridge $bridge */
        $bridge = app(BookingSupplierIntegrationBridge::class);
        $bridge->dispatchFlightBookingPlaceholder($booking->fresh());

        $booking->refresh();
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame(BookingSupplierIntegrationBridge::HOOK_CONFIRMED, $booking->supplier_flight_hook_status);
        $this->assertSame('confirmed', data_get($notes, 'supplier_booking.status'));
        $this->assertNull(data_get($notes, 'supplier_payment'));

        Http::assertNotSent(function ($request): bool {
            return $request->url() === 'https://api.duffel.com/air/payments';
        });
    }
}
