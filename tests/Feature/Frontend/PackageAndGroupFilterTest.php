<?php

namespace Tests\Feature\Frontend;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageAndGroupFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_packages_index_filters_by_price_range(): void
    {
        $agency = Agency::query()->create(['name' => 'F Agency', 'code' => 'FAG', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Makkah', 'slug' => 'makkah-f', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'umrah-f', 'is_active' => true]);

        TravelPackage::query()->create([
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'agency_id' => $agency->id,
            'title' => 'Budget Trip',
            'slug' => 'budget-trip-f',
            'base_price' => 50000,
            'is_active' => true,
            'currency' => 'PKR',
        ]);

        TravelPackage::query()->create([
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'agency_id' => $agency->id,
            'title' => 'Luxury Trip',
            'slug' => 'luxury-trip-f',
            'base_price' => 500000,
            'is_active' => true,
            'currency' => 'PKR',
        ]);

        $this->get(route('frontend.packages.index', [
            'price_min' => 100000,
            'price_max' => 600000,
        ]))
            ->assertOk()
            ->assertSee('Luxury Trip')
            ->assertDontSee('Budget Trip');
    }

    public function test_groups_index_filters_by_status(): void
    {
        $agency = Agency::query()->create(['name' => 'G Agency', 'code' => 'GAG', 'is_active' => true]);

        TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Open Group',
            'slug' => 'open-group-f',
            'status' => 'open',
            'departure_date' => now()->addMonth()->toDateString(),
        ]);

        TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Closed Group',
            'slug' => 'closed-group-f',
            'status' => 'closed',
            'departure_date' => now()->addMonths(2)->toDateString(),
        ]);

        $this->get(route('frontend.groups.index', ['status' => 'open']))
            ->assertOk()
            ->assertSee('Open Group')
            ->assertDontSee('Closed Group');
    }
}
