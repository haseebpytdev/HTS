<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\Traveler;
use App\Services\Booking\BookingLifecycleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuffelCancellationPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_booking_cancel_route_remains_approval_gated(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.bookings.cancel');
        $this->assertNotNull($route);
        $middleware = $route !== null ? $route->gatherMiddleware() : [];

        $this->assertContains('approval.gate:booking_force_cancel,booking', $middleware);
    }

    public function test_cancellation_preparation_adds_supplier_metadata_and_history_without_forcing_remote_cancel(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.credential_environment' => 'production',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.orders_path' => '/air/orders',
            'duffel.cancellation_live_enabled' => false,
            'duffel.credentials.production.client_id' => 'duffel',
            'duffel.credentials.production.client_secret' => 'duffel_test_integration_token',
        ]);
        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'tenant_id' => null,
            'environment' => 'production',
            'name' => 'Duffel Cancellation Preparation',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $booking = Booking::query()->create([
            'quotation_id' => 1,
            'agency_id' => 1,
            'booking_number' => 'BK-DF8-001',
            'status' => BookingStatus::Confirmed->value,
            'total_amount' => 500.00,
            'currency' => 'USD',
            'payment_status' => 'unpaid',
            'internal_notes' => (string) json_encode([
                'supplier_booking' => [
                    'provider' => 'duffel',
                    'booking_reference' => 'ord_cancel_001',
                    'correlation_id' => 'duffel-cancel-correlation-1',
                ],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $booking->travelers()->create([
            'first_name' => 'Cancel',
            'last_name' => 'Candidate',
            'traveler_type' => Traveler::TYPE_ADULT,
        ]);

        Http::fake([
            'https://api.duffel.com/air/orders/ord_cancel_001' => Http::response([
                'data' => [
                    'type' => 'order',
                    'id' => 'ord_cancel_001',
                    'status' => 'confirmed',
                    'booking_reference' => 'PNR-CANCEL-1',
                    'total_currency' => 'USD',
                    'total_amount' => '500.00',
                ],
            ], 200),
            'https://api.duffel.com/air/orders/ord_cancel_001/actions/cancel' => Http::response([], 201),
        ]);

        /** @var BookingLifecycleManager $manager */
        $manager = app(BookingLifecycleManager::class);
        $manager->cancel($booking->fresh(), 'Customer requested cancellation');

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled->value, $booking->status);
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame('prepared', data_get($notes, 'supplier_cancellation.status'));
        $this->assertTrue((bool) data_get($notes, 'supplier_cancellation.requires_approval'));
        $this->assertFalse((bool) data_get($notes, 'supplier_cancellation.remote_cancellation_enabled'));
        $this->assertSame('prepared_not_implemented', data_get($notes, 'supplier_change_groundwork.change_support_status'));
        $this->assertTrue((bool) data_get($notes, 'supplier_change_groundwork.approval_required'));
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'event' => 'supplier_cancel_prepared',
        ]);

        $meta = BookingStatusHistory::query()
            ->where('booking_id', $booking->id)
            ->where('event', 'supplier_cancel_prepared')
            ->value('meta');
        $this->assertSame('booking_force_cancel', data_get($meta, 'approval_request_type'));

        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'booking_cancel_prepare')
                ->exists()
        );
        Http::assertNotSent(function ($request): bool {
            return $request->url() === 'https://api.duffel.com/air/orders/ord_cancel_001/actions/cancel';
        });
    }
}
