<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingSupplierCostAndMarginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_capture_supplier_cost_on_booking(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::query()->create(['name' => 'P&L Agency', 'code' => 'PNL-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::query()->create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-PNL-'.uniqid(),
            'customer_name' => 'Finance User',
            'currency' => 'PKR',
            'subtotal' => '1000.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '1000.00',
            'status' => 'draft',
        ]);
        $booking = Booking::query()->create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-PNL-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '1000.00',
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.bookings.supplier-cost.update', $booking), [
                'supplier_cost_total' => '760.00',
                'supplier_cost_currency' => 'PKR',
            ])
            ->assertRedirect(route('admin.bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'supplier_cost_total' => 760,
            'supplier_cost_currency' => 'PKR',
            'supplier_cost_recorded_by_user_id' => $admin->id,
        ]);
    }

    public function test_gross_margin_matches_sell_total_minus_supplier_cost(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::query()->create(['name' => 'Margin Agency', 'code' => 'MAR-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::query()->create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-MAR-'.uniqid(),
            'customer_name' => 'Margin Client',
            'currency' => 'PKR',
            'subtotal' => '1200.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '1200.00',
            'status' => 'draft',
        ]);
        $booking = Booking::query()->create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-MAR-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '1200.00',
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.bookings.supplier-cost.update', $booking), [
                'supplier_cost_total' => '820.50',
                'supplier_cost_currency' => 'PKR',
            ])
            ->assertRedirect(route('admin.bookings.show', $booking));

        $booking->refresh();
        $sell = (float) $booking->total_amount;
        $cost = (float) $booking->supplier_cost_total;
        $this->assertEqualsWithDelta(379.5, $sell - $cost, 0.01);
    }
}
