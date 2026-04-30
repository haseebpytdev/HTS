<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentFlowType;
use App\Enums\PaymentRecordStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\ApprovalRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingPaymentHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function makeBooking(): Booking
    {
        $agency = Agency::create(['name' => 'HTTP Pay', 'code' => 'HP-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-H-'.uniqid(),
            'customer_name' => 'HTTP Client',
            'currency' => 'PKR',
            'subtotal' => '800.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '800.00',
            'status' => 'draft',
        ]);

        return Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-H-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '800.00',
            'currency' => 'PKR',
        ]);
    }

    public function test_guest_cannot_post_deposit(): void
    {
        $booking = $this->makeBooking();

        $this->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '100',
        ])->assertRedirect(route('login'));
    }

    public function test_admin_can_record_deposit_via_http(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();

        $response = $this->actingAs($admin)->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '150.00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => PaymentRecordStatus::Completed->value,
            'amount' => 150,
        ]);
    }

    public function test_deposit_validation_rejects_amount_over_balance_due(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();

        $this->actingAs($admin)->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '900.00',
        ])->assertSessionHasErrors('amount');
    }

    public function test_admin_can_top_up_agency_wallet_via_http(): void
    {
        $admin = $this->adminUser();
        $agency = Agency::create(['name' => 'Wallet HTTP', 'code' => 'W-'.uniqid(), 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.agencies.wallet.top-up', $agency), [
            'amount' => '200.00',
            'currency' => 'PKR',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('agency_wallets', [
            'agency_id' => $agency->id,
        ]);
    }

    public function test_refund_redirects_back_to_booking_when_payment_has_booking(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();

        $this->actingAs($admin)->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '100.00',
        ]);

        $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->approveRefund($admin, $payment);

        $response = $this->actingAs($admin)->post(route('admin.payments.refund', $payment), [
            'amount' => '25.00',
            'reason' => 'Test',
        ]);

        $response->assertRedirect(route('admin.bookings.show', $booking));
        $response->assertSessionHas('success');
    }

    public function test_refund_without_approval_gate_returns_423(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();

        $this->actingAs($admin)->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '100.00',
        ])->assertRedirect();

        $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), [
            'amount' => '10.00',
            'reason' => 'No approval on file',
        ])->assertStatus(423);
    }

    public function test_admin_can_record_balance_payment_via_http(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();

        $this->actingAs($admin)->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '100.00',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.payments.balance', $booking), [
            'amount' => '250.00',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => PaymentRecordStatus::Completed->value,
            'amount' => 250,
        ]);
    }

    public function test_admin_can_record_full_payment_for_remaining_balance(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();

        $this->actingAs($admin)->post(route('admin.bookings.payments.deposit', $booking), [
            'amount' => '200.00',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.bookings.payments.full', $booking), [])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'flow_type' => PaymentFlowType::FullPayment->value,
            'amount' => 600,
            'status' => PaymentRecordStatus::Completed->value,
        ]);
    }

    public function test_admin_can_settle_booking_from_wallet_after_top_up(): void
    {
        $admin = $this->adminUser();
        $booking = $this->makeBooking();
        $agency = $booking->agency;

        $this->actingAs($admin)->post(route('admin.agencies.wallet.top-up', $agency), [
            'amount' => '1000.00',
            'currency' => 'PKR',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.payments.wallet', $booking), [
            'amount' => '800.00',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => PaymentRecordStatus::Completed->value,
            'amount' => 800,
        ]);
    }

    private function approveRefund(User $reviewer, Payment $payment): void
    {
        ApprovalRequest::query()->create([
            'request_type' => 'payment_refund',
            'status' => 'approved',
            'requested_by_user_id' => $reviewer->id,
            'reviewed_by_user_id' => $reviewer->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'reason' => 'Test refund approval',
            'submitted_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);
    }
}
