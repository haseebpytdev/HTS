<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_or_agency_dashboards(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('agency.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_role_cannot_access_agency_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($admin)
            ->get(route('agency.dashboard'))
            ->assertForbidden();
    }

    public function test_agency_user_cannot_access_admin_dashboard(): void
    {
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY_USER->value,
        ]);

        $this->actingAs($agencyUser)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_sales_operator_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SALES_OPERATOR->value,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_agency_user_cannot_open_admin_quotations_index(): void
    {
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY_USER->value,
        ]);

        $this->actingAs($agencyUser)
            ->get(route('admin.quotations.index'))
            ->assertForbidden();
    }

    public function test_agency_user_cannot_open_admin_bookings_index(): void
    {
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY_USER->value,
        ]);

        $this->actingAs($agencyUser)
            ->get(route('admin.bookings.index'))
            ->assertForbidden();
    }

    public function test_customer_guard_cannot_open_admin_dashboard(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
