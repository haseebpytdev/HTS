<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesUmrahQuotationDependencies;
use Tests\TestCase;

class DuffelAdminFlightSelectionTest extends TestCase
{
    use CreatesUmrahQuotationDependencies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_store_duffel_flight_selection_in_quotation_and_seed_booking_request(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $this->actingAs($admin)
            ->post(route('admin.quotations.store'), $this->quotationPayload($deps, [
                'customer_name' => 'Duffel Admin Selection',
                'integration_provider' => 'duffel',
                'integration_offer_reference' => 'off_admin_001',
                'integration_correlation_id' => 'admin-duffel-selection-correlation',
                'integration_selected_passengers_csv' => 'pas_001,pas_002',
            ]))
            ->assertRedirect();

        $quotation = Quotation::query()->where('customer_name', 'Duffel Admin Selection')->firstOrFail();
        $flightMeta = data_get($quotation->items()->where('item_type', 'flight')->firstOrFail()->meta, 'integration');
        $this->assertSame('duffel', data_get($flightMeta, 'provider'));
        $this->assertSame('off_admin_001', data_get($flightMeta, 'offer_reference'));
        $this->assertSame(['pas_001', 'pas_002'], data_get($flightMeta, 'selected_passengers'));

        $this->actingAs($admin)
            ->post(route('admin.quotations.bookings.store', $quotation))
            ->assertRedirect();

        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame('duffel', data_get($notes, 'supplier_booking_request.provider'));
        $this->assertSame('off_admin_001', data_get($notes, 'supplier_booking_request.offer_reference'));
        $this->assertSame('admin-duffel-selection-correlation', data_get($notes, 'supplier_booking_request.correlation_id'));
        $this->assertSame(['pas_001', 'pas_002'], data_get($notes, 'supplier_booking_request.selected_passengers'));
    }

    public function test_admin_confirm_flow_runs_duffel_pricing_then_booking_via_bridge(): void
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
            'duffel.hold_order_payment_enabled' => false,
            'duffel.credentials.production.client_id' => 'duffel',
            'duffel.credentials.production.client_secret' => 'duffel_test_integration_token',
        ]);

        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'tenant_id' => null,
            'environment' => 'production',
            'name' => 'Duffel Admin Workflow Connection',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        Http::fake([
            'https://api.duffel.com/air/offers/off_admin_001*' => Http::response([
                'data' => [
                    'type' => 'offer',
                    'id' => 'off_admin_001',
                    'status' => 'available',
                    'total_currency' => 'USD',
                    'total_amount' => '280.00',
                    'base_amount' => '230.00',
                    'tax_amount' => '50.00',
                ],
            ], 200),
            'https://api.duffel.com/air/orders' => Http::response([
                'data' => [
                    'type' => 'order',
                    'id' => 'ord_admin_001',
                    'status' => 'confirmed',
                    'booking_reference' => 'PNRADM1',
                    'created_at' => '2026-06-10T09:00:00Z',
                    'total_currency' => 'USD',
                    'total_amount' => '280.00',
                    'base_amount' => '230.00',
                    'tax_amount' => '50.00',
                    'passengers' => [[
                        'type' => 'adult',
                        'given_name' => 'Adult',
                        'family_name' => '1',
                    ]],
                ],
            ], 201),
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();
        $this->actingAs($admin)->post(route('admin.quotations.store'), $this->quotationPayload($deps, [
            'customer_name' => 'Duffel Admin Confirm Flow',
            'integration_provider' => 'duffel',
            'integration_offer_reference' => 'off_admin_001',
            'integration_correlation_id' => 'admin-duffel-confirm-correlation',
            'integration_selected_passengers_csv' => 'pas_001',
        ]))->assertRedirect();
        $quotation = Quotation::query()->where('customer_name', 'Duffel Admin Confirm Flow')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.bookings.store', $quotation))->assertRedirect();

        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.bookings.confirm', $booking))->assertRedirect();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->supplier_flight_hook_status);
        $notes = json_decode((string) $booking->internal_notes, true);
        $this->assertSame('duffel', data_get($notes, 'supplier_booking.provider'));
        $this->assertSame('off_admin_001', data_get($notes, 'supplier_booking.offer_reference'));
        $this->assertSame('ord_admin_001', data_get($notes, 'supplier_booking.booking_reference'));
        $this->assertSame('PNRADM1', data_get($notes, 'supplier_booking.pnr'));

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/air/offers/off_admin_001')
            && $request->hasHeader('X-Correlation-ID'));
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.duffel.com/air/orders'
            && data_get($request->data(), 'data.selected_offers.0') === 'off_admin_001');

        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'pricing')
                ->exists()
        );
        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'booking_create')
                ->exists()
        );
    }

    /**
     * @param  array{agency: \App\Models\Agency, makkah_hotel_rate_id: int, madinah_hotel_rate_id: int, visa_rate_id: int, transport_rate_id: int, flight_entry_id: int}  $deps
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quotationPayload(array $deps, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Duffel Internal Admin',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 2,
            'makkah_room_basis' => 'double',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 2,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'markup_type' => 'fixed',
            'markup_value' => 0,
            'integration_provider' => null,
            'integration_offer_reference' => null,
            'integration_correlation_id' => null,
            'integration_selected_passengers_csv' => null,
        ], $overrides);
    }
}
