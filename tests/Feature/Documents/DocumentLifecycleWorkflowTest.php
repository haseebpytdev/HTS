<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Jobs\ScanBookingDocumentJob;
use App\Models\Agency;
use App\Models\AsyncTaskRun;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Async\AsyncTaskTracker;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentLifecycleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_upload_starts_with_pending_scan_when_queue_is_faked(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('pending-passport.pdf', 30, 'application/pdf'),
        ])->assertRedirect();

        $doc = BookingDocument::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();
        $this->assertSame('pending_scan', $doc->virus_scan_status);
        Queue::assertPushed(ScanBookingDocumentJob::class);
    }

    public function test_scan_job_marks_clean_and_infected_states(): void
    {
        $booking = $this->seedBooking();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $clean = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'original_name' => 'clean.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'booking-documents/'.$booking->id.'/clean.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'is_customer_visible' => true,
        ]);
        $infected = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'other',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'original_name' => 'payload.exe',
            'storage_disk' => 'local',
            'storage_path' => 'booking-documents/'.$booking->id.'/payload.bin',
            'mime_type' => 'application/x-msdownload',
            'size_bytes' => 128,
            'is_customer_visible' => false,
        ]);

        $cleanTask = AsyncTaskRun::query()->create([
            'task_type' => 'document_virus_scan',
            'status' => 'queued',
            'reference_type' => BookingDocument::class,
            'reference_id' => $clean->id,
        ]);
        $infectedTask = AsyncTaskRun::query()->create([
            'task_type' => 'document_virus_scan',
            'status' => 'queued',
            'reference_type' => BookingDocument::class,
            'reference_id' => $infected->id,
        ]);

        Config::set('documents.scanning.mode', 'stub');
        Config::set('documents.scanning.stub_status', 'clean');
        (new ScanBookingDocumentJob($clean->id, $cleanTask->id))->handle(
            app(AsyncTaskTracker::class),
            app(\App\Services\Documents\DocumentScanService::class)
        );
        Config::set('documents.scanning.stub_status', 'infected');
        (new ScanBookingDocumentJob($infected->id, $infectedTask->id))->handle(
            app(AsyncTaskTracker::class),
            app(\App\Services\Documents\DocumentScanService::class)
        );

        $this->assertSame('clean', $clean->fresh()->virus_scan_status);
        $this->assertSame('infected', $infected->fresh()->virus_scan_status);
    }

    public function test_failed_scan_state_is_blocked_for_download_and_can_be_archived(): void
    {
        Storage::fake('local');
        $booking = $this->seedBooking();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $doc = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'original_name' => 'failure.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'booking-documents/'.$booking->id.'/failure.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'is_customer_visible' => true,
            'virus_scan_status' => 'failed',
            'virus_scan_note' => 'Scanner failed: backend timeout',
            'virus_scanned_at' => now(),
        ]);

        $this->assertSame('failed', $doc->fresh()->virus_scan_status);
        $this->assertStringContainsString('Scanner failed', (string) $doc->fresh()->virus_scan_note);

        Storage::disk('local')->put($doc->storage_path, 'failed-doc');
        $this->actingAs($admin)->get(route('admin.bookings.documents.download', [$booking, $doc]))->assertStatus(423);
        $this->actingAs($admin)->post(route('admin.bookings.documents.archive', [$booking, $doc]), [
            'archive_note' => 'archived after failed scan review',
        ])->assertRedirect();

        $this->assertSoftDeleted('booking_documents', ['id' => $doc->id]);
        $this->assertDatabaseHas('booking_document_audits', ['booking_document_id' => $doc->id, 'action' => 'archived']);
    }

    public function test_versioning_archive_and_download_audits_are_recorded(): void
    {
        Storage::fake('local');
        Config::set('documents.scanning.mode', 'stub');
        Config::set('documents.scanning.stub_status', 'clean');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('passport-v1.pdf', 30, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('passport-v2.pdf', 30, 'application/pdf'),
        ])->assertRedirect();

        $v1 = BookingDocument::withTrashed()->where('booking_id', $booking->id)->where('version_number', 1)->firstOrFail();
        $v2 = BookingDocument::withTrashed()->where('booking_id', $booking->id)->where('version_number', 2)->firstOrFail();
        $this->assertSame($v2->id, $v1->superseded_by_document_id);

        $this->actingAs($admin)->get(route('admin.bookings.documents.download', [$booking, $v2]))
            ->assertOk();
        $this->actingAs($admin)->post(route('admin.bookings.documents.archive', [$booking, $v2]), [
            'archive_note' => 'superseded',
        ])->assertRedirect();

        $this->assertSoftDeleted('booking_documents', ['id' => $v2->id]);
        $this->assertDatabaseHas('booking_document_audits', ['booking_document_id' => $v2->id, 'action' => 'downloaded']);
        $this->assertDatabaseHas('booking_document_audits', ['booking_document_id' => $v2->id, 'action' => 'archived']);
    }

    private function seedBooking(): Booking
    {
        $agency = Agency::create(['name' => 'Doc Agency', 'code' => 'DOC-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-DOC-'.uniqid(),
            'customer_name' => 'Doc User',
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
            'booking_number' => 'B-DOC-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '500.00',
            'currency' => 'PKR',
        ]);
    }
}
