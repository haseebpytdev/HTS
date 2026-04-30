<?php

namespace Tests\Feature\Customer;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerBookingDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_download_approved_visible_document(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create();
        $agency = Agency::create(['name' => 'CDL Agency', 'code' => 'CDL-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-CDL-'.uniqid(),
            'customer_name' => $customer->fullName(),
            'currency' => 'PKR',
            'subtotal' => '800.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '800.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_number' => 'B-CDL-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '800.00',
            'currency' => 'PKR',
        ]);

        $path = 'booking-documents/'.$booking->id.'/customer-doc.pdf';
        Storage::disk('local')->put($path, 'demo');

        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_customer_id' => $customer->id,
            'document_type' => 'passport',
            'validation_status' => 'approved',
            'virus_scan_status' => 'clean',
            'original_name' => 'customer-doc.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.bookings.documents.download', [$booking, $doc]))
            ->assertOk()
            ->assertDownload('customer-doc.pdf');

        $this->assertDatabaseHas('booking_document_audits', [
            'booking_document_id' => $doc->id,
            'action' => 'downloaded',
            'actor_customer_id' => $customer->id,
            'actor_role' => 'customer',
        ]);
    }

    public function test_customer_cannot_download_pending_document(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create();
        $agency = Agency::create(['name' => 'CDL Agency2', 'code' => 'CDL2-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-CDL2-'.uniqid(),
            'customer_name' => $customer->fullName(),
            'currency' => 'PKR',
            'subtotal' => '800.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '800.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_number' => 'B-CDL2-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '800.00',
            'currency' => 'PKR',
        ]);

        $path = 'booking-documents/'.$booking->id.'/pending.pdf';
        Storage::disk('local')->put($path, 'demo');

        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_customer_id' => $customer->id,
            'document_type' => 'passport',
            'validation_status' => 'pending',
            'virus_scan_status' => 'pending_scan',
            'original_name' => 'pending.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.bookings.documents.download', [$booking, $doc]))
            ->assertForbidden();
    }
}
