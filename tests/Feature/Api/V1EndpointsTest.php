<?php

namespace Tests\Feature\Api;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V1EndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_ok_payload(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonPath('data.module', 'api-v1');
    }

    public function test_packages_index_returns_paginated_json(): void
    {
        $agency = Agency::query()->create(['name' => 'Test Agency', 'code' => 'TST', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Makkah', 'slug' => 'makkah']);
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'umrah']);
        TravelPackage::query()->create([
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'agency_id' => $agency->id,
            'title' => 'Demo Package',
            'slug' => 'demo-package',
            'is_active' => true,
            'currency' => 'PKR',
        ]);

        $response = $this->getJson('/api/v1/packages');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
        $response->assertJsonPath('data.0.slug', 'demo-package');
    }

    public function test_post_inquiry_quote_source_creates_record(): void
    {
        $response = $this->postJson('/api/v1/inquiries', [
            'source' => 'quote',
            'name' => 'API Customer',
            'email' => 'api@example.com',
            'adults' => 2,
            'message' => 'Need a quote for April.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.source', 'quote');
        $response->assertJsonPath('data.name', 'API Customer');
        $this->assertDatabaseHas('inquiries', [
            'email' => 'api@example.com',
            'status' => Inquiry::STATUS_NEW,
        ]);
    }

    public function test_post_quotation_requires_validation(): void
    {
        $response = $this->postJson('/api/v1/quotations', []);

        $response->assertUnprocessable();
        $response->assertJsonStructure(['message', 'errors']);
    }

    public function test_get_quotation_returns_resource(): void
    {
        $agency = Agency::query()->create(['name' => 'Q Agency', 'code' => 'QAG', 'is_active' => true]);
        $quotation = Quotation::query()->create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-API-1',
            'customer_name' => 'Client',
            'currency' => 'PKR',
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'status' => 'draft',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ]);

        $response = $this->getJson('/api/v1/quotations/'.$quotation->id);

        $response->assertOk();
        $response->assertJsonPath('data.quote_number', 'Q-API-1');
        $response->assertJsonPath('data.customer_name', 'Client');
    }

    public function test_groups_index_respects_status_filter_and_resource_shape(): void
    {
        $agency = Agency::query()->create(['name' => 'Grp API', 'code' => 'GAP'.substr(uniqid(), -4), 'is_active' => true]);

        TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Visible Group',
            'slug' => 'visible-grp-'.uniqid(),
            'status' => 'open',
            'departure_date' => now()->addWeek()->toDateString(),
        ]);

        TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Hidden Group',
            'slug' => 'hidden-grp-'.uniqid(),
            'status' => 'closed',
            'departure_date' => now()->addMonth()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/groups?status=open');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
        $response->assertJsonFragment(['name' => 'Visible Group']);
        $this->assertStringNotContainsString('Hidden Group', $response->getContent());
    }

    public function test_hotels_index_returns_expected_json_structure(): void
    {
        Hotel::query()->create([
            'name' => 'API Hotel',
            'slug' => 'api-hotel-'.uniqid(),
            'city' => 'Makkah',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hotels');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
        $response->assertJsonFragment(['name' => 'API Hotel']);
    }
}
