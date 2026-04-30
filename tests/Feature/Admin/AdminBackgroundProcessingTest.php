<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Jobs\GenerateAnalyticsCsvReportJob;
use App\Jobs\ScanBookingDocumentJob;
use App\Models\Agency;
use App\Models\AsyncTaskRun;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBackgroundProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_document_upload_queues_async_virus_scan_job_and_task(): void
    {
        Queue::fake();
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::create(['name' => 'BG Agency', 'code' => 'BGA-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-BG-'.uniqid(),
            'customer_name' => 'BG User',
            'currency' => 'PKR',
            'subtotal' => '100.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '100.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-BG-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '100.00',
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('async_task_runs', [
            'task_type' => 'document_virus_scan',
            'status' => 'queued',
            'requested_by_user_id' => $admin->id,
        ]);
        Queue::assertPushed(ScanBookingDocumentJob::class);
    }

    public function test_admin_can_queue_large_analytics_csv_bundle(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->post(route('admin.analytics.queue-bundle-csv'), [
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('async_task_runs', [
            'task_type' => 'analytics_csv_bundle',
            'status' => 'queued',
            'requested_by_user_id' => $admin->id,
        ]);
        Queue::assertPushed(GenerateAnalyticsCsvReportJob::class);
    }
}
