<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Quotation;
use App\Models\Traveler;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUmrahQuotationDependencies;
use Tests\TestCase;

class BookingEngineFlowTest extends TestCase
{
    use CreatesUmrahQuotationDependencies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_quotation_converts_to_draft_booking_with_items_and_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Booking Engine Client',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 3,
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
            'markup_type' => 'percentage',
            'markup_value' => 5,
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload)->assertRedirect();

        $quotation = Quotation::query()->where('customer_name', 'Booking Engine Client')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.quotations.bookings.store', $quotation))
            ->assertRedirect();

        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame(BookingStatus::Draft->value, $booking->status);
        $this->assertGreaterThan(0, $booking->items()->count());
        $this->assertSame(2, $booking->travelers()->count());
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'event' => 'created_from_quotation',
        ]);
    }

    public function test_hold_confirm_sets_supplier_hook_placeholders_and_cancel_records_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Lifecycle Client',
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
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload);
        $quotation = Quotation::query()->where('customer_name', 'Lifecycle Client')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.bookings.store', $quotation));

        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.bookings.hold', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame(BookingStatus::OnHold->value, $booking->status);
        $this->assertNotNull($booking->hold_expires_at);

        $this->actingAs($admin)->post(route('admin.bookings.confirm', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed->value, $booking->status);
        $this->assertSame('queued', $booking->supplier_flight_hook_status);
        $this->assertSame('queued', $booking->supplier_hotel_hook_status);

        ApprovalRequest::query()->create([
            'request_type' => 'booking_force_cancel',
            'status' => 'approved',
            'requested_by_user_id' => $admin->id,
            'reviewed_by_user_id' => $admin->id,
            'reference_type' => Booking::class,
            'reference_id' => $booking->id,
            'review_note' => 'Approved for test cancellation flow',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.cancel', $booking), [
            'reason' => 'Customer requested',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled->value, $booking->status);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'to_status' => BookingStatus::Cancelled->value,
            'event' => 'cancel',
        ]);
    }

    public function test_status_history_records_amendment_between_confirm_and_cancel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Amendment History Client',
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
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload);
        $quotation = Quotation::query()->where('customer_name', 'Amendment History Client')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.bookings.store', $quotation));
        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.bookings.hold', $booking))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.confirm', $booking))->assertRedirect();

        $booking->refresh();
        $this->actingAs($admin)->patch(route('admin.bookings.amend', $booking), [
            'amend_reason' => 'Passport name alignment',
            'travelers' => [
                [
                    'first_name' => 'Amended',
                    'last_name' => 'Passenger',
                    'traveler_type' => Traveler::TYPE_ADULT,
                ],
            ],
        ])->assertRedirect();

        ApprovalRequest::query()->create([
            'request_type' => 'booking_force_cancel',
            'status' => 'approved',
            'requested_by_user_id' => $admin->id,
            'reviewed_by_user_id' => $admin->id,
            'reference_type' => Booking::class,
            'reference_id' => $booking->id,
            'review_note' => 'Approved for amendment flow cancellation',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.cancel', $booking), [
            'reason' => 'Customer withdrew',
        ])->assertRedirect();

        $booking->refresh();
        $events = BookingStatusHistory::query()
            ->where('booking_id', $booking->id)
            ->orderBy('id')
            ->pluck('event')
            ->all();
        $this->assertSame(
            ['created_from_quotation', 'hold', 'confirm', 'amended', 'cancel'],
            $events
        );
    }

    public function test_invoice_view_issues_invoice_number_once(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Invoice Client',
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
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload);
        $quotation = Quotation::query()->where('customer_name', 'Invoice Client')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.bookings.store', $quotation));
        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.bookings.confirm', $booking));

        $this->actingAs($admin)->get(route('admin.bookings.invoice', $booking))->assertOk();
        $booking->refresh();
        $this->assertNotNull($booking->invoice_number);

        $n = $booking->invoice_number;
        $this->actingAs($admin)->get(route('admin.bookings.invoice', $booking))->assertOk();
        $booking->refresh();
        $this->assertSame($n, $booking->invoice_number);
    }

    public function test_voucher_view_renders_for_confirmed_booking(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Voucher Client',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 1,
            'makkah_room_basis' => 'double',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 1,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'markup_type' => 'fixed',
            'markup_value' => 0,
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload);
        $quotation = Quotation::query()->where('customer_name', 'Voucher Client')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.bookings.store', $quotation));
        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.bookings.hold', $booking))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.confirm', $booking))->assertRedirect();

        $booking->refresh();
        $this->actingAs($admin)
            ->get(route('admin.bookings.voucher', $booking))
            ->assertOk()
            ->assertSee($booking->booking_number, false);
    }
}
