<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentScanStateEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_cannot_approve_non_clean_scan_results(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        [$booking] = $this->seedBookingContext();

        foreach (['pending_scan', 'infected', 'suspicious', 'failed', 'skipped'] as $scanStatus) {
            $doc = BookingDocument::create([
                'booking_id' => $booking->id,
                'uploaded_by_user_id' => $admin->id,
                'document_type' => 'passport',
                'validation_status' => 'pending',
                'virus_scan_status' => $scanStatus,
                'original_name' => "{$scanStatus}.pdf",
                'storage_disk' => 'local',
                'storage_path' => "booking-documents/{$booking->id}/{$scanStatus}.pdf",
                'mime_type' => 'application/pdf',
                'size_bytes' => 4,
                'is_customer_visible' => true,
            ]);

            $this->actingAs($admin)->patch(route('admin.bookings.documents.validate', [$booking, $doc]), [
                'validation_status' => 'approved',
                'is_customer_visible' => '1',
                'validation_note' => 'approve attempt',
            ])->assertSessionHasErrors('validation_status');
        }
    }

    public function test_admin_can_approve_clean_document(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        [$booking] = $this->seedBookingContext();
        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'validation_status' => 'pending',
            'virus_scan_status' => 'clean',
            'original_name' => 'clean.pdf',
            'storage_disk' => 'local',
            'storage_path' => "booking-documents/{$booking->id}/clean.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.bookings.documents.validate', [$booking, $doc]), [
            'validation_status' => 'approved',
            'is_customer_visible' => '1',
            'validation_note' => 'clean-approved',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_documents', [
            'id' => $doc->id,
            'validation_status' => 'approved',
        ]);
    }

    public function test_admin_and_customer_download_are_blocked_when_scan_is_not_clean(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        [$booking, $customer] = $this->seedBookingContext();

        $blocked = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'validation_status' => 'approved',
            'virus_scan_status' => 'infected',
            'original_name' => 'blocked.pdf',
            'storage_disk' => 'local',
            'storage_path' => "booking-documents/{$booking->id}/blocked.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);
        Storage::disk('local')->put($blocked->storage_path, 'demo');

        $this->actingAs($admin)
            ->get(route('admin.bookings.documents.download', [$booking, $blocked]))
            ->assertStatus(423);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.bookings.documents.download', [$booking, $blocked]))
            ->assertStatus(423);

        $clean = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'validation_status' => 'approved',
            'virus_scan_status' => 'clean',
            'original_name' => 'clean.pdf',
            'storage_disk' => 'local',
            'storage_path' => "booking-documents/{$booking->id}/clean.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);
        Storage::disk('local')->put($clean->storage_path, 'demo');

        $this->actingAs($admin)
            ->get(route('admin.bookings.documents.download', [$booking, $clean]))
            ->assertOk();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.bookings.documents.download', [$booking, $clean]))
            ->assertOk();
    }

    /**
     * @return array{0: Booking, 1: Customer}
     */
    private function seedBookingContext(): array
    {
        $customer = Customer::factory()->create();
        $agency = Agency::create(['name' => 'State Agency', 'code' => 'STATE-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-STATE-'.uniqid(),
            'customer_name' => $customer->fullName(),
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
            'customer_id' => $customer->id,
            'booking_number' => 'B-STATE-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '500.00',
            'currency' => 'PKR',
        ]);

        return [$booking, $customer];
    }
}
