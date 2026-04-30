<?php

namespace Tests\Feature\Frontend;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Inquiry;
use App\Models\TravelPackage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendInquirySubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_quote_inquiry_post_creates_inquiry_row(): void
    {
        $response = $this->post(route('frontend.inquiries.store-quote'), [
            'name' => 'Quote Lead',
            'email' => 'quote-lead@example.com',
            'adults' => 3,
            'children' => 1,
            'message' => 'Looking for Umrah in Shawwal.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inquiries', [
            'name' => 'Quote Lead',
            'email' => 'quote-lead@example.com',
            'source' => 'quote',
            'status' => Inquiry::STATUS_NEW,
        ]);
    }

    public function test_package_inquiry_post_creates_inquiry_with_package_id(): void
    {
        $agency = Agency::query()->create(['name' => 'Pkg Agency', 'code' => 'PAG', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Makkah', 'slug' => 'makkah-p15', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'umrah-p15', 'is_active' => true]);
        $package = TravelPackage::query()->create([
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'agency_id' => $agency->id,
            'title' => 'Gold Package',
            'slug' => 'gold-package-p15',
            'is_active' => true,
            'currency' => 'PKR',
        ]);

        $response = $this->post(route('frontend.inquiries.store-package'), [
            'package_id' => $package->id,
            'name' => 'Package Lead',
            'email' => 'pkg@example.com',
            'adults' => 2,
            'message' => 'Interested in this package.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inquiries', [
            'package_id' => $package->id,
            'source' => 'package',
            'name' => 'Package Lead',
            'status' => Inquiry::STATUS_NEW,
        ]);
    }
}
