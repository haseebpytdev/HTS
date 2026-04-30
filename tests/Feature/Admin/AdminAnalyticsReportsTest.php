<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_analytics_reports_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Analytics Reports')
            ->assertSee('Agency performance')
            ->assertSee('Package performance')
            ->assertSee('Payment aging');
    }

    public function test_admin_can_download_analytics_csvs_and_see_drilldown_links(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);
        $agency = Agency::query()->create([
            'name' => 'A-Analytics',
            'code' => 'AA-'.uniqid(),
            'is_active' => true,
        ]);
        $package = TravelPackage::query()->create([
            'agency_id' => $agency->id,
            'title' => 'Pkg Analytics',
            'slug' => 'pkg-analytics-'.uniqid(),
            'duration_days' => 7,
            'base_price' => 100,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
        Inquiry::query()->create([
            'source' => 'package',
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'name' => 'Lead',
            'status' => Inquiry::STATUS_NEW,
        ]);
        $quotation = Quotation::query()->create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-AN-'.uniqid(),
            'customer_name' => 'Pnl User',
            'currency' => 'PKR',
            'subtotal' => '1000.00',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '1000.00',
            'status' => 'draft',
        ]);
        Booking::query()->create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-AN-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => '1000.00',
            'supplier_cost_total' => '760.00',
            'supplier_cost_currency' => 'PKR',
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.agencies-csv', ['from_date' => now()->startOfMonth()->toDateString(), 'to_date' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->actingAs($admin)
            ->get(route('admin.analytics.packages-csv', ['from_date' => now()->startOfMonth()->toDateString(), 'to_date' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Download Agency CSV')
            ->assertSee('Weekly trend')
            ->assertSee('Supplier costs')
            ->assertSee('Net margin total')
            ->assertSee(route('admin.inquiries.index', ['package_id' => $package->id]), false);
    }
}
