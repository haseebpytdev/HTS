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
use App\Services\Documents\DocumentScanService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRescanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_failed_document_rescan_moves_from_pending_to_clean_and_audits_events(): void
    {
        Storage::fake('local');
        Queue::fake();
        Config::set('documents.scanning.mode', 'stub');
        Config::set('documents.scanning.stub_status', 'clean');
        Config::set('documents.scanning.stub_message', 'rescan-clean');

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();
        $path = "booking-documents/{$booking->id}/failed.pdf";
        Storage::disk('local')->put($path, 'failed-doc');
        $doc = BookingDocument::create([
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $admin->id,
            'document_type' => 'passport',
            'document_group_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
            'validation_status' => 'pending',
            'virus_scan_status' => 'failed',
            'virus_scan_note' => 'Initial scanner timeout',
            'original_name' => 'failed.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
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

        /** @var AsyncTaskRun $task */
        $task = AsyncTaskRun::query()
            ->where('task_type', 'document_virus_scan')
            ->where('reference_id', $doc->id)
            ->latest('id')
            ->firstOrFail();

        (new ScanBookingDocumentJob($doc->id, $task->id))->handle(
            app(AsyncTaskTracker::class),
            app(DocumentScanService::class)
        );

        $doc->refresh();
        $task->refresh();
        $this->assertSame('clean', $doc->virus_scan_status);
        $this->assertNotNull($doc->virus_scanned_at);
        $this->assertSame('completed', $task->status);
        $this->assertSame('clean', data_get($task->result, 'virus_scan_status'));
        $this->assertSame('stub', data_get($task->result, 'engine'));
    }

    private function seedBooking(): Booking
    {
        $agency = Agency::create(['name' => 'Rescan Agency', 'code' => 'RSC-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-RSC-'.uniqid(),
            'customer_name' => 'Rescan User',
            'currency' => 'PKR',
            'subtotal' => '250.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '250.00',
            'status' => 'draft',
        ]);

        return Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-RSC-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '250.00',
            'currency' => 'PKR',
        ]);
    }
}
