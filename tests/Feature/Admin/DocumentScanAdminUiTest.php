<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Jobs\ScanBookingDocumentJob;
use App\Models\Agency;
use App\Models\AsyncTaskRun;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentScanAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_booking_show_displays_scan_status_engine_and_rescan_controls(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();
        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'virus_scan_status' => 'suspicious',
            'virus_scanned_at' => now(),
            'virus_scan_note' => 'Suspicious payload detected.',
            'original_name' => 'passport.pdf',
            'storage_disk' => 'local',
            'storage_path' => "booking-documents/{$booking->id}/passport.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);

        AsyncTaskRun::query()->create([
            'task_type' => 'document_virus_scan',
            'status' => 'completed',
            'reference_type' => (new BookingDocument)->getMorphClass(),
            'reference_id' => $doc->id,
            'result' => [
                'engine' => 'clamav',
                'message' => 'Heuristic suspicious pattern.',
                'checked_at' => now()->toIso8601String(),
            ],
        ]);

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('suspicious')
            ->assertSee('Engine: clamav')
            ->assertSee('Quarantined')
            ->assertSee('Rescan');
    }

    public function test_admin_can_trigger_rescan_for_quarantined_document(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();
        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'virus_scan_status' => 'failed',
            'virus_scan_note' => 'scan timeout',
            'original_name' => 'failed.pdf',
            'storage_disk' => 'local',
            'storage_path' => "booking-documents/{$booking->id}/failed.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.bookings.documents.rescan', [$booking, $doc]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame('pending_scan', $doc->virus_scan_status);
        $this->assertDatabaseHas('booking_document_audits', [
            'booking_document_id' => $doc->id,
            'action' => 'rescan_requested',
            'actor_user_id' => $admin->id,
        ]);
        Queue::assertPushed(ScanBookingDocumentJob::class);
    }

    private function seedBooking(): Booking
    {
        $agency = Agency::create(['name' => 'Admin UI Agency', 'code' => 'AUI-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-AUI-'.uniqid(),
            'customer_name' => 'Admin UI User',
            'currency' => 'PKR',
            'subtotal' => '300.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '300.00',
            'status' => 'draft',
        ]);

        return Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-AUI-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '300.00',
            'currency' => 'PKR',
        ]);
    }
}
