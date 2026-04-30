<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingDocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_update_document_validation_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::create(['name' => 'Doc Validate', 'code' => 'DV-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-V-'.uniqid(),
            'customer_name' => 'Validation User',
            'currency' => 'PKR',
            'subtotal' => '500.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '500.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-V-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '500.00',
            'currency' => 'PKR',
        ]);
        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'validation_status' => 'pending',
            'virus_scan_status' => 'clean',
            'original_name' => 'passport.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'booking-documents/'.$booking->id.'/passport.pdf',
            'size_bytes' => 1000,
            'is_customer_visible' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.bookings.documents.validate', [$booking, $doc]), [
            'validation_status' => 'approved',
            'is_customer_visible' => '1',
            'validation_note' => 'Clear and valid.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('booking_documents', [
            'id' => $doc->id,
            'validation_status' => 'approved',
            'validation_note' => 'Clear and valid.',
            'validated_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('booking_document_audits', [
            'booking_document_id' => $doc->id,
            'action' => 'validated',
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
        ]);
    }
}
