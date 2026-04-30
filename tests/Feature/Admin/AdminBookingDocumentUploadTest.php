<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBookingDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_upload_booking_document(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::create(['name' => 'Doc Agency', 'code' => 'DOC-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-D-'.uniqid(),
            'customer_name' => 'Doc User',
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
            'booking_number' => 'B-D-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '1000.00',
            'currency' => 'PKR',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'notes' => 'Passport front page',
            'file' => UploadedFile::fake()->create('passport.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('booking_documents', [
            'booking_id' => $booking->id,
            'document_type' => 'passport',
            'validation_status' => 'pending',
            'uploaded_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('booking_document_audits', [
            'booking_id' => $booking->id,
            'action' => 'uploaded',
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
        ]);
    }
}
