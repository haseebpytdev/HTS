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

class BookingDocumentVirusScanFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_upload_marks_pending_scan_and_queues_job(): void
    {
        Storage::fake('local');
        Queue::fake();
        Config::set('documents.scanning.mode', 'stub');
        Config::set('documents.scanning.stub_status', 'clean');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('scan-flow.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $doc = BookingDocument::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();
        $this->assertSame('pending_scan', $doc->virus_scan_status);
        Queue::assertPushed(ScanBookingDocumentJob::class);
    }

    public function test_scan_job_persists_clean_infected_suspicious_failed_states(): void
    {
        Storage::fake('local');
        $booking = $this->seedBooking();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $statuses = ['clean', 'infected', 'suspicious', 'failed'];

        foreach ($statuses as $index => $status) {
            Config::set('documents.scanning.mode', 'stub');
            Config::set('documents.scanning.stub_status', $status);
            Config::set('documents.scanning.stub_message', 'Stub '.$status);

            $path = 'booking-documents/'.$booking->id."/flow-{$status}-{$index}.pdf";
            Storage::disk('local')->put($path, 'stub-'.$status);
            $doc = BookingDocument::query()->create([
                'booking_id' => $booking->id,
                'uploaded_by_user_id' => $admin->id,
                'document_type' => 'passport',
                'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'version_number' => 1,
                'validation_status' => 'pending',
                'virus_scan_status' => 'pending_scan',
                'original_name' => "flow-{$status}.pdf",
                'storage_disk' => 'local',
                'storage_path' => $path,
                'mime_type' => 'application/pdf',
                'size_bytes' => 10,
                'is_customer_visible' => true,
            ]);
            $task = AsyncTaskRun::query()->create([
                'task_type' => 'document_virus_scan',
                'status' => 'queued',
                'reference_type' => BookingDocument::class,
                'reference_id' => $doc->id,
            ]);

            (new ScanBookingDocumentJob($doc->id, $task->id))->handle(
                app(AsyncTaskTracker::class),
                app(\App\Services\Documents\DocumentScanService::class)
            );

            $doc->refresh();
            $this->assertSame($status, $doc->virus_scan_status);
            $this->assertNotNull($doc->virus_scanned_at);
            $this->assertNotEmpty($doc->virus_scan_note);
            if ($status === 'failed') {
                $this->assertNotSame('clean', $doc->virus_scan_status);
            }
        }
    }

    private function seedBooking(): Booking
    {
        $agency = Agency::create(['name' => 'Scan Agency', 'code' => 'SCAN-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-SCAN-'.uniqid(),
            'customer_name' => 'Scan User',
            'currency' => 'PKR',
            'subtotal' => '100.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '100.00',
            'status' => 'draft',
        ]);

        return Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-SCAN-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '100.00',
            'currency' => 'PKR',
        ]);
    }
}
