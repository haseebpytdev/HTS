<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\GroupImage;
use App\Models\Inquiry;
use App\Models\PackageDeparture;
use App\Models\PackageImage;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

class FrontendDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lhrAgency = Agency::where('code', 'APS-LHR')->first();

        $makkah = Destination::updateOrCreate(
            ['slug' => 'makkah'],
            ['name' => 'Makkah', 'country' => 'Saudi Arabia', 'city' => 'Makkah', 'is_active' => true]
        );
        $madinah = Destination::updateOrCreate(
            ['slug' => 'madinah'],
            ['name' => 'Madinah', 'country' => 'Saudi Arabia', 'city' => 'Madinah', 'is_active' => true]
        );

        $umrahCategory = Category::updateOrCreate(
            ['slug' => 'umrah-packages'],
            ['name' => 'Umrah Packages', 'type' => 'package', 'sort_order' => 1, 'is_active' => true]
        );
        $groupCategory = Category::updateOrCreate(
            ['slug' => 'group-tickets'],
            ['name' => 'Group Tickets', 'type' => 'group', 'sort_order' => 2, 'is_active' => true]
        );

        $package = TravelPackage::updateOrCreate(
            ['slug' => 'ramadan-umrah-premium'],
            [
                'category_id' => $umrahCategory->id,
                'destination_id' => $makkah->id,
                'agency_id' => $lhrAgency?->id,
                'title' => 'Ramadan Umrah Premium',
                'excerpt' => '10 days premium Umrah with Makkah and Madinah stay.',
                'description' => 'Includes flights, visa, hotels, and transport.',
                'duration_days' => 10,
                'base_price' => 325000,
                'currency' => 'PKR',
                'is_featured' => true,
                'is_active' => true,
            ]
        );

        PackageDeparture::updateOrCreate(
            ['package_id' => $package->id, 'departure_date' => now()->addMonth()->toDateString()],
            [
                'return_date' => now()->addMonth()->addDays(10)->toDateString(),
                'capacity' => 40,
                'seats_left' => 18,
                'price' => 325000,
            ]
        );

        PackageImage::updateOrCreate(
            ['package_id' => $package->id, 'sort_order' => 1],
            ['image_path' => 'assets/images/demo/package-1.jpg', 'alt_text' => 'Ramadan Umrah Premium', 'is_cover' => true]
        );

        $group = TravelGroup::updateOrCreate(
            ['slug' => 'shawwal-group-april'],
            [
                'package_id' => $package->id,
                'agency_id' => $lhrAgency?->id,
                'name' => 'Shawwal Group - April',
                'group_type' => $groupCategory->slug,
                'departure_date' => now()->addMonths(2)->toDateString(),
                'return_date' => now()->addMonths(2)->addDays(10)->toDateString(),
                'capacity' => 30,
                'seats_left' => 12,
                'status' => 'open',
                'notes' => 'Popular family batch.',
            ]
        );

        GroupImage::updateOrCreate(
            ['group_id' => $group->id, 'sort_order' => 1],
            ['image_path' => 'assets/images/demo/group-1.jpg', 'alt_text' => 'Shawwal Group', 'is_cover' => true]
        );

        Inquiry::updateOrCreate(
            ['email' => 'customer@example.com', 'package_id' => $package->id],
            [
                'source' => 'frontend',
                'agency_id' => $lhrAgency?->id,
                'destination_id' => $madinah->id,
                'group_id' => $group->id,
                'name' => 'Ahmed Khan',
                'phone' => '+92-300-0000000',
                'travel_date' => now()->addMonths(2)->toDateString(),
                'adults' => 2,
                'children' => 1,
                'budget' => 700000,
                'currency' => 'PKR',
                'message' => 'Need a family package with near haram hotel.',
                'status' => 'new',
            ]
        );
    }
}
