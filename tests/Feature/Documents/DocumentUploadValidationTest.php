<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_upload_rejects_script_like_file_before_scan_queue(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'other',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('payload.php', 10, 'text/x-php'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('booking_documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_upload_enforces_max_file_size_before_scan_queue(): void
    {
        Storage::fake('local');
        Queue::fake();
        Config::set('documents.upload.max_file_size_bytes', 1024);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('too-large.pdf', 5, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('booking_documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_upload_accepts_pdf_and_image_and_uses_safe_storage_path(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $booking = $this->seedBooking();

        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->create('passport-original.pdf', 50, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'other',
            'is_customer_visible' => '1',
            'file' => UploadedFile::fake()->image('traveler-photo.png', 500, 500),
        ])->assertRedirect();

        $docs = BookingDocument::query()->where('booking_id', $booking->id)->orderBy('id')->get();
        $this->assertCount(2, $docs);

        foreach ($docs as $doc) {
            $this->assertStringStartsWith("booking-documents/{$booking->id}/uploads/", $doc->storage_path);
            $this->assertStringNotContainsString('passport-original.pdf', $doc->storage_path);
            $this->assertStringNotContainsString('traveler-photo.png', $doc->storage_path);
            Storage::disk($doc->storage_disk)->assertExists($doc->storage_path);
        }
    }

    private function seedBooking(): Booking
    {
        $agency = Agency::create(['name' => 'Upload Agency', 'code' => 'UP-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-UP-'.uniqid(),
            'customer_name' => 'Upload User',
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
            'booking_number' => 'B-UP-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '300.00',
            'currency' => 'PKR',
        ]);
    }
}
