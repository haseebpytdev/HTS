<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $hqAgency = Agency::where('code', 'APS-HQ')->first();
        $lhrAgency = Agency::where('code', 'APS-LHR')->first();
        $khiAgency = Agency::where('code', 'APS-KHI')->first();

        $fallbackTenantId = Tenant::defaultModel()->id;

        $password = Hash::make('password');

        User::updateOrCreate(
            ['email' => 'superadmin@apnasafar.test'],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'role' => UserRole::SUPER_ADMIN->value,
                'agency_id' => $hqAgency?->id,
                'tenant_id' => $hqAgency?->tenant_id ?? $fallbackTenantId,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@apnasafar.test'],
            [
                'name' => 'Operations Admin',
                'password' => $password,
                'role' => UserRole::ADMIN->value,
                'agency_id' => $hqAgency?->id,
                'tenant_id' => $hqAgency?->tenant_id ?? $fallbackTenantId,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'sales@apnasafar.test'],
            [
                'name' => 'Sales Operator',
                'password' => $password,
                'role' => UserRole::SALES_OPERATOR->value,
                'agency_id' => $hqAgency?->id,
                'tenant_id' => $hqAgency?->tenant_id ?? $fallbackTenantId,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'agency.lhr@apnasafar.test'],
            [
                'name' => 'Lahore Agency User',
                'password' => $password,
                'role' => UserRole::AGENCY_USER->value,
                'agency_id' => $lhrAgency?->id,
                'tenant_id' => $lhrAgency?->tenant_id ?? $fallbackTenantId,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'agency.khi@apnasafar.test'],
            [
                'name' => 'Karachi Agency User',
                'password' => $password,
                'role' => UserRole::AGENCY_USER->value,
                'agency_id' => $khiAgency?->id,
                'tenant_id' => $khiAgency?->tenant_id ?? $fallbackTenantId,
                'email_verified_at' => now(),
            ]
        );
    }
}
