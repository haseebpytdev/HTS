<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::defaultModel()->id;

        Agency::updateOrCreate(
            ['code' => 'APS-HQ'],
            ['name' => 'Hayat Travel Solutions Head Office', 'is_active' => true, 'tenant_id' => $tenantId]
        );

        Agency::updateOrCreate(
            ['code' => 'APS-LHR'],
            ['name' => 'Hayat Travel Solutions Lahore Agency', 'is_active' => true, 'tenant_id' => $tenantId]
        );

        Agency::updateOrCreate(
            ['code' => 'APS-KHI'],
            ['name' => 'Hayat Travel Solutions Karachi Agency', 'is_active' => true, 'tenant_id' => $tenantId]
        );
    }
}
