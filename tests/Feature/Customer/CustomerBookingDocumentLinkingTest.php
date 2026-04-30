<?php

namespace Tests\Feature\Customer;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingDocumentLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_show_displays_only_approved_visible_linked_documents(): void
    {
        $customer = Customer::factory()->create();
        $agency = Agency::create(['name' => 'Customer Docs', 'code' => 'CD-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-CD-'.uniqid(),
            'customer_name' => $customer->fullName(),
            'currency' => 'PKR',
            'subtotal' => '1000.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '1000.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_number' => 'B-CD-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '1000.00',
            'currency' => 'PKR',
        ]);

        BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_customer_id' => $customer->id,
            'document_type' => 'passport',
            'validation_status' => 'approved',
            'virus_scan_status' => 'clean',
            'original_name' => 'approved.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'booking-documents/'.$booking->id.'/approved.pdf',
            'size_bytes' => 1000,
            'is_customer_visible' => true,
        ]);
        BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_customer_id' => $customer->id,
            'document_type' => 'visa',
            'validation_status' => 'pending',
            'virus_scan_status' => 'pending_scan',
            'original_name' => 'pending.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'booking-documents/'.$booking->id.'/pending.pdf',
            'size_bytes' => 1000,
            'is_customer_visible' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('approved.pdf')
            ->assertDontSee('pending.pdf');
    }
}
