<?php

namespace Tests\Feature\Frontend;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPackageSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_package_pages_reflect_admin_managed_title_updates(): void
    {
        $agency = Agency::query()->create(['name' => 'Public Agency', 'code' => 'PUB-PKG', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Madinah', 'slug' => 'madinah-public', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'umrah-public', 'is_active' => true]);

        $package = TravelPackage::query()->create([
            'agency_id' => $agency->id,
            'destination_id' => $destination->id,
            'category_id' => $category->id,
            'title' => 'Old Public Package',
            'slug' => 'old-public-package',
            'base_price' => 90000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get(route('frontend.packages.index'))
            ->assertOk()
            ->assertSee('Old Public Package');

        $package->update(['title' => 'Updated Public Package']);

        $this->get(route('frontend.packages.index'))
            ->assertOk()
            ->assertSee('Updated Public Package')
            ->assertDontSee('Old Public Package');

        $this->get(route('frontend.packages.show', $package->slug))
            ->assertOk()
            ->assertSee('Updated Public Package');
    }

    public function test_inactive_package_is_hidden_from_public_list_and_detail(): void
    {
        $agency = Agency::query()->create(['name' => 'Visibility Agency', 'code' => 'VIS-PKG', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Jeddah', 'slug' => 'jeddah-public', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Hajj', 'slug' => 'hajj-public', 'is_active' => true]);

        $package = TravelPackage::query()->create([
            'agency_id' => $agency->id,
            'destination_id' => $destination->id,
            'category_id' => $category->id,
            'title' => 'Visibility Package',
            'slug' => 'visibility-package',
            'base_price' => 120000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->get(route('frontend.packages.index'))
            ->assertOk()
            ->assertSee('Visibility Package');

        $package->update(['is_active' => false]);

        $this->get(route('frontend.packages.index'))
            ->assertOk()
            ->assertDontSee('Visibility Package');

        $this->get(route('frontend.packages.show', $package->slug))
            ->assertNotFound();
    }
}
