<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentQuarantineProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_infected_and_suspicious_documents_are_blocked_and_audited_on_download_attempts(): void
    {
        Storage::fake('local');
        [$booking, $admin, $customer] = $this->seedContext();

        foreach (['infected', 'suspicious'] as $status) {
            $doc = BookingDocument::create([
                'booking_id' => $booking->id,
                'uploaded_by_user_id' => $admin->id,
                'document_type' => 'passport',
                'validation_status' => 'approved',
                'virus_scan_status' => $status,
                'virus_scan_note' => "Blocked due to {$status}",
                'original_name' => "{$status}.pdf",
                'storage_disk' => 'local',
                'storage_path' => "booking-documents/{$booking->id}/{$status}.pdf",
                'mime_type' => 'application/pdf',
                'size_bytes' => 4,
                'is_customer_visible' => true,
            ]);
            Storage::disk('local')->put($doc->storage_path, 'demo');

            $this->actingAs($admin)
                ->get(route('admin.bookings.documents.download', [$booking, $doc]))
                ->assertStatus(423);

            $this->actingAs($customer, 'customer')
                ->get(route('customer.bookings.documents.download', [$booking, $doc]))
                ->assertStatus(423);

            $this->assertDatabaseHas('booking_document_audits', [
                'booking_document_id' => $doc->id,
                'action' => 'download_blocked',
                'actor_role' => 'admin',
            ]);
            $this->assertDatabaseHas('booking_document_audits', [
                'booking_document_id' => $doc->id,
                'action' => 'download_blocked',
                'actor_role' => 'customer',
            ]);
        }
    }

    public function test_quarantine_is_explicit_on_model_for_unsafe_scan_states(): void
    {
        $doc = new BookingDocument(['virus_scan_status' => 'infected']);
        $this->assertTrue($doc->isQuarantined());
        $this->assertFalse($doc->isScanSafe());

        $clean = new BookingDocument(['virus_scan_status' => 'clean']);
        $this->assertFalse($clean->isQuarantined());
        $this->assertTrue($clean->isScanSafe());
    }

    /**
     * @return array{0: Booking, 1: User, 2: Customer}
     */
    private function seedContext(): array
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $customer = Customer::factory()->create();
        $agency = Agency::create(['name' => 'Quarantine Agency', 'code' => 'QA-'.uniqid(), 'is_active' => true]);
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-QA-'.uniqid(),
            'customer_name' => $customer->fullName(),
            'currency' => 'PKR',
            'subtotal' => '700.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '700.00',
            'status' => 'draft',
        ]);
        $booking = Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_number' => 'B-QA-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '700.00',
            'currency' => 'PKR',
        ]);

        return [$booking, $admin, $customer];
    }
}
