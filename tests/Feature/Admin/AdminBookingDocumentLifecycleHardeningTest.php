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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBookingDocumentLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_upload_creates_version_chain_and_audit_entries(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        [$booking] = $this->seedBookingContext();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('passport-v1.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('passport-v2.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $v1 = BookingDocument::withTrashed()->where('booking_id', $booking->id)->where('version_number', 1)->firstOrFail();
        $v2 = BookingDocument::withTrashed()->where('booking_id', $booking->id)->where('version_number', 2)->firstOrFail();

        $this->assertSame($v1->document_group_uuid, $v2->document_group_uuid);
        $this->assertSame($v2->id, $v1->superseded_by_document_id);

        $this->assertDatabaseCount('booking_document_audits', 2);
        $this->assertDatabaseHas('booking_document_audits', [
            'booking_document_id' => $v1->id,
            'action' => 'uploaded',
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('booking_document_audits', [
            'booking_document_id' => $v2->id,
            'action' => 'uploaded',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_validate_archive_and_download_write_immutable_audits(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        [$booking] = $this->seedBookingContext();

        $path = 'booking-documents/'.$booking->id.'/doc.pdf';
        Storage::disk('local')->put($path, 'demo');
        $doc = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'virus_scan_status' => 'clean',
            'original_name' => 'doc.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 4,
            'is_customer_visible' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.bookings.documents.validate', [$booking, $doc]), [
            'validation_status' => 'approved',
            'is_customer_visible' => '1',
            'validation_note' => 'ok',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.bookings.documents.download', [$booking, $doc]))
            ->assertOk()
            ->assertDownload('doc.pdf');

        $this->actingAs($admin)->post(route('admin.bookings.documents.archive', [$booking, $doc]), [
            'archive_note' => 'obsolete',
        ])->assertRedirect();

        $this->assertSoftDeleted('booking_documents', ['id' => $doc->id]);
        $this->assertDatabaseHas('booking_document_audits', ['booking_document_id' => $doc->id, 'action' => 'validated']);
        $this->assertDatabaseHas('booking_document_audits', ['booking_document_id' => $doc->id, 'action' => 'downloaded']);
        $this->assertDatabaseHas('booking_document_audits', ['booking_document_id' => $doc->id, 'action' => 'archived']);
    }

    /**
     * @return array{0: Booking}
     */
    private function seedBookingContext(): array
    {
        $agency = Agency::create(['name' => 'LC Agency', 'code' => 'LCA-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-LC-'.uniqid(),
            'customer_name' => 'Lifecycle User',
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
            'booking_number' => 'B-LC-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '500.00',
            'currency' => 'PKR',
        ]);

        return [$booking];
    }
}
