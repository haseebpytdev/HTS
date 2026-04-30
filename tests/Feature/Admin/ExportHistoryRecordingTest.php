<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ExportHistory;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportHistoryRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_pdf_export_creates_history_row(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $quotation = Quotation::create([
            'agency_id' => null,
            'user_id' => $admin->id,
            'quote_number' => 'QTN-TEST-EXPORT-001',
            'customer_name' => 'Test Customer',
            'currency' => 'PKR',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.quotations.pdf', $quotation))
            ->assertOk();

        $this->assertDatabaseHas('export_histories', [
            'user_id' => $admin->id,
            'export_type' => ExportHistory::TYPE_QUOTATION_PDF,
            'reference_id' => $quotation->id,
        ]);
    }

    public function test_inquiries_csv_export_creates_history_row(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.exports.inquiries-csv'))
            ->assertOk();

        $this->assertDatabaseHas('export_histories', [
            'user_id' => $admin->id,
            'export_type' => ExportHistory::TYPE_INQUIRIES_CSV,
        ]);
    }
}
