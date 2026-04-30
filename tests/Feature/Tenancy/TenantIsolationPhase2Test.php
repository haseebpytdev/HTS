<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\ApprovalRequest;
use App\Models\ApplicationSetting;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Services\Tenancy\TenancySettings;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantIsolationPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_when_scoping_disabled_queries_are_not_filtered_by_tenant(): void
    {
        config(['tenancy.tenant_scoping_enabled' => false]);

        $t1 = Tenant::factory()->create(['slug' => 't1-'.uniqid()]);
        $t2 = Tenant::factory()->create(['slug' => 't2-'.uniqid()]);
        Agency::query()->create(['name' => 'A1', 'code' => 'A1-'.uniqid(), 'is_active' => true, 'tenant_id' => $t1->id]);
        Agency::query()->create(['name' => 'A2', 'code' => 'A2-'.uniqid(), 'is_active' => true, 'tenant_id' => $t2->id]);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'tenant_id' => $t1->id,
            'agency_id' => null,
        ]);

        $this->actingAs($admin);
        $this->assertSame(2, Agency::query()->count());
    }

    public function test_when_scoping_enabled_admin_only_sees_own_tenant_agencies(): void
    {
        config(['tenancy.tenant_scoping_enabled' => true]);

        $t1 = Tenant::factory()->create(['slug' => 't1b-'.uniqid()]);
        $t2 = Tenant::factory()->create(['slug' => 't2b-'.uniqid()]);
        Agency::query()->create(['name' => 'A1b', 'code' => 'B1-'.uniqid(), 'is_active' => true, 'tenant_id' => $t1->id]);
        Agency::query()->create(['name' => 'A2b', 'code' => 'B2-'.uniqid(), 'is_active' => true, 'tenant_id' => $t2->id]);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'tenant_id' => $t1->id,
            'agency_id' => null,
        ]);

        $this->actingAs($admin);
        $names = Agency::query()->orderBy('name')->pluck('name')->all();
        $this->assertSame(['A1b'], $names);
    }

    public function test_super_admin_sees_all_agencies_when_scoping_enabled(): void
    {
        config(['tenancy.tenant_scoping_enabled' => true]);

        $t1 = Tenant::factory()->create(['slug' => 't1c-'.uniqid()]);
        $t2 = Tenant::factory()->create(['slug' => 't2c-'.uniqid()]);
        Agency::query()->create(['name' => 'C1', 'code' => 'C1-'.uniqid(), 'is_active' => true, 'tenant_id' => $t1->id]);
        Agency::query()->create(['name' => 'C2', 'code' => 'C2-'.uniqid(), 'is_active' => true, 'tenant_id' => $t2->id]);

        $super = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'tenant_id' => $t1->id,
            'agency_id' => null,
        ]);

        $this->actingAs($super);
        $this->assertSame(2, Agency::query()->count());
    }

    public function test_booking_policy_denies_cross_tenant_when_scoping_enabled(): void
    {
        config(['tenancy.tenant_scoping_enabled' => true]);

        $t1 = Tenant::factory()->create(['slug' => 't1d-'.uniqid()]);
        $t2 = Tenant::factory()->create(['slug' => 't2d-'.uniqid()]);
        $a2 = Agency::query()->create(['name' => 'Ax', 'code' => 'AX-'.uniqid(), 'is_active' => true, 'tenant_id' => $t2->id]);
        $q = Quotation::query()->create([
            'agency_id' => $a2->id,
            'quote_number' => 'Q-'.uniqid(),
            'customer_name' => 'X',
            'currency' => 'PKR',
            'subtotal' => '1',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '1',
            'status' => 'draft',
        ]);
        $booking = Booking::query()->withoutGlobalScopes()->create([
            'quotation_id' => $q->id,
            'agency_id' => $a2->id,
            'booking_number' => 'BK-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '1',
            'currency' => 'PKR',
        ]);
        $booking->load('agency');

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'tenant_id' => $t1->id,
            'agency_id' => null,
        ]);

        $policy = new BookingPolicy;
        $this->assertFalse($policy->view($admin, $booking));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $booking));
    }

    public function test_application_setting_overrides_config_for_scoping_flag(): void
    {
        config(['tenancy.tenant_scoping_enabled' => false]);

        ApplicationSetting::setValue(TenancySettings::KEY_TENANT_SCOPING_ENABLED, '1');

        $svc = app(TenancySettings::class);
        $this->assertTrue($svc->tenantScopingEnabled());
    }

    public function test_super_admin_can_update_tenancy_settings(): void
    {
        $super = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'agency_id' => null,
        ]);

        ApprovalRequest::query()->create([
            'request_type' => 'tenancy_toggle',
            'status' => 'approved',
            'requested_by_user_id' => $super->id,
            'reviewed_by_user_id' => $super->id,
            'reference_type' => null,
            'reference_id' => null,
            'reason' => 'Test tenancy toggle approval',
            'submitted_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($super)
            ->put(route('admin.system.tenancy.update'), [
                'tenant_scoping_enabled' => '1',
            ])
            ->assertRedirect(route('admin.system.tenancy.edit'));

        $this->assertSame('1', ApplicationSetting::getValue(TenancySettings::KEY_TENANT_SCOPING_ENABLED));
    }

    public function test_non_super_admin_cannot_update_tenancy_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.system.tenancy.update'), [
                'tenant_scoping_enabled' => '1',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_resolve_cross_tenant_booking_from_url(): void
    {
        config(['tenancy.tenant_scoping_enabled' => true]);

        $t1 = Tenant::factory()->create(['slug' => 't1e-'.uniqid()]);
        $t2 = Tenant::factory()->create(['slug' => 't2e-'.uniqid()]);
        $a2 = Agency::query()->create(['name' => 'CrossBk', 'code' => 'XB-'.uniqid(), 'is_active' => true, 'tenant_id' => $t2->id]);
        $q = Quotation::query()->create([
            'agency_id' => $a2->id,
            'quote_number' => 'Q-XB-'.uniqid(),
            'customer_name' => 'Cross',
            'currency' => 'PKR',
            'subtotal' => '100',
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => '100',
            'status' => 'draft',
        ]);
        $booking = Booking::query()->withoutGlobalScopes()->create([
            'quotation_id' => $q->id,
            'agency_id' => $a2->id,
            'booking_number' => 'BK-XB-'.uniqid(),
            'status' => 'draft',
            'total_amount' => '100',
            'currency' => 'PKR',
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'tenant_id' => $t1->id,
            'agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertNotFound();
    }

    public function test_super_admin_tenancy_update_without_approval_gate_returns_423(): void
    {
        $super = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'agency_id' => null,
        ]);

        $this->actingAs($super)
            ->put(route('admin.system.tenancy.update'), [
                'tenant_scoping_enabled' => '1',
            ])
            ->assertStatus(423);
    }
}
