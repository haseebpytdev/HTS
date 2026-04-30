<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AgencySeeder::class,
            UserRoleSeeder::class,
            InventorySeeder::class,
            FrontendDemoSeeder::class,
            QuotationDemoSeeder::class,
            CmsSeeder::class,
        ]);
    }
}
