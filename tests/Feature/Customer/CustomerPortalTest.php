<?php

namespace Tests\Feature\Customer;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerSavedTraveler;
use App\Models\Quotation;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function makeBookingForCustomer(Customer $customer): Booking
    {
        $agency = Agency::create(['name' => 'Portal Agency', 'code' => 'P-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-P-'.uniqid(),
            'customer_name' => $customer->fullName(),
            'currency' => 'PKR',
            'subtotal' => '500.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '500.00',
            'status' => 'draft',
        ]);

        return Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_number' => 'B-P-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '500.00',
            'currency' => 'PKR',
        ]);
    }

    public function test_customer_can_register_and_reach_dashboard(): void
    {
        $response = $this->post(route('customer.register'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
    }

    public function test_guest_cannot_view_customer_bookings(): void
    {
        $this->get(route('customer.bookings.index'))->assertRedirect(route('customer.login'));
    }

    public function test_customer_sees_only_own_bookings_and_can_pay(): void
    {
        $customer = Customer::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('password')]);
        $other = Customer::factory()->create();
        $booking = $this->makeBookingForCustomer($customer);
        $otherBooking = $this->makeBookingForCustomer($other);

        $this->actingAs($customer, 'customer');

        $this->get(route('customer.bookings.index'))->assertOk()->assertSee($booking->booking_number, false)
            ->assertDontSee($otherBooking->booking_number, false);

        $this->get(route('customer.bookings.show', $otherBooking))->assertForbidden();

        $this->post(route('customer.bookings.payments.deposit', $booking), [
            'amount' => '100.00',
        ])->assertRedirect();

        $this->post(route('customer.bookings.payments.balance', $otherBooking), [
            'amount' => '50.00',
        ])->assertForbidden();

        $this->post(route('customer.bookings.payments.full', $otherBooking), [])->assertForbidden();

        $this->get(route('customer.bookings.voucher', $booking))->assertOk();
        $this->get(route('customer.bookings.invoice', $booking))->assertOk();
    }

    public function test_saved_travelers_crud_scoped_to_customer(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->post(route('customer.saved-travelers.store'), [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'traveler_type' => 'adult',
        ])->assertRedirect(route('customer.saved-travelers.index'));

        $traveler = CustomerSavedTraveler::query()->where('first_name', 'Ali')->firstOrFail();
        $this->assertSame($customer->id, $traveler->customer_id);

        $this->put(route('customer.saved-travelers.update', $traveler), [
            'first_name' => 'Ali',
            'last_name' => 'Updated',
            'traveler_type' => 'adult',
        ])->assertRedirect(route('customer.saved-travelers.index'));

        $this->delete(route('customer.saved-travelers.destroy', $traveler))->assertRedirect(route('customer.saved-travelers.index'));
        $this->assertDatabaseMissing('customer_saved_travelers', ['id' => $traveler->id]);
    }
}
