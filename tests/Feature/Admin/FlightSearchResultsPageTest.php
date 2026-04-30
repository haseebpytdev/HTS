<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SupplierSearchSession;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FlightSearchResultsPageTest extends TestCase
{
    public function test_super_admin_can_open_flight_search_results_pages(): void
    {
        config([
            'permissions.role_matrix.super_admin' => ['*'],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($superAdmin);

        $this->get(route('admin.integrations.search-results.index'))
            ->assertOk()
            ->assertSee('Flight Search Results');

        if (Schema::hasTable('supplier_search_sessions')) {
            $session = SupplierSearchSession::query()->create([
                'correlation_id' => 'corr-smoke-001',
                'provider' => 'sabre',
                'environment' => 'sandbox',
                'status' => 'completed',
                'internal_request_snapshot' => ['origin' => 'KHI', 'destination' => 'JED'],
                'search_results_summary' => ['offers' => 0],
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $this->get(route('admin.integrations.search-results.show', $session))
                ->assertOk()
                ->assertSee('corr-smoke-001');
        }
    }
}
