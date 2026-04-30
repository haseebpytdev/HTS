<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBookingDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_booking_document(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::create(['name' => 'DL Agency', 'code' => 'DLA-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-DL-'.uniqid(),
            'customer_name' => 'DL User',
            'currency' => 'PKR',
            'subtotal' => '400.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '400.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-DL-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '400.00',
            'currency' => 'PKR',
        ]);

        $path = 'booking-documents/'.$booking->id.'/doc.pdf';
        Storage::disk('local')->put($path, 'demo');

        $document = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'validation_status' => 'approved',
            'virus_scan_status' => 'clean',
            'original_name' => 'doc.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.documents.download', [$booking, $document]))
            ->assertOk()
            ->assertDownload('doc.pdf');

        $this->assertDatabaseHas('booking_document_audits', [
            'booking_document_id' => $document->id,
            'action' => 'downloaded',
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
        ]);
    }
}
