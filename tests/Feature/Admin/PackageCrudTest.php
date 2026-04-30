<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensurePackageTablesExist();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensurePackageTablesExist(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('type')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('destinations')) {
            Schema::create('destinations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('destination_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('excerpt')->nullable();
                $table->longText('description')->nullable();
                $table->unsignedSmallInteger('duration_days')->nullable();
                $table->decimal('base_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('PKR');
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_list_filter_create_update_and_delete_packages(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'umrah', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Makkah', 'slug' => 'makkah', 'is_active' => true]);

        TravelPackage::query()->create([
            'title' => 'Starter Package',
            'slug' => 'starter-package',
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'base_price' => 100000,
            'currency' => 'PKR',
            'is_featured' => true,
            'is_active' => true,
        ]);
        TravelPackage::query()->create([
            'title' => 'Inactive Package',
            'slug' => 'inactive-package',
            'base_price' => 50000,
            'currency' => 'PKR',
            'is_featured' => false,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.packages.index', [
                'q' => 'Starter',
                'destination_id' => $destination->id,
                'category_id' => $category->id,
                'is_featured' => 1,
                'is_active' => 1,
            ]))
            ->assertOk()
            ->assertSee('Starter Package')
            ->assertDontSee('Inactive Package');

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'title' => 'Premium Package',
                'slug' => 'premium-package',
                'destination_id' => $destination->id,
                'category_id' => $category->id,
                'overview' => 'Overview text',
                'itinerary' => 'Day 1, Day 2',
                'inclusions' => 'Hotel, transport',
                'exclusions' => 'Personal expense',
                'duration_days' => 10,
                'base_price' => 150000,
                'currency' => 'pkr',
                'is_featured' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $package = TravelPackage::query()->where('title', 'Premium Package')->firstOrFail();
        $this->assertSame('PKR', $package->currency);

        $this->actingAs($admin)
            ->put(route('admin.packages.update', $package), [
                'title' => 'Premium Package Updated',
                'slug' => 'premium-package-updated',
                'destination_id' => $destination->id,
                'category_id' => $category->id,
                'overview' => 'Updated overview',
                'itinerary' => 'Updated itinerary',
                'inclusions' => 'Updated inclusions',
                'exclusions' => 'Updated exclusions',
                'duration_days' => 12,
                'base_price' => 175000,
                'currency' => 'SAR',
                'is_featured' => 0,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.packages.edit', $package));

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'title' => 'Premium Package Updated',
            'slug' => 'premium-package-updated',
            'currency' => 'SAR',
            'is_featured' => 0,
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.packages.destroy', $package))
            ->assertRedirect(route('admin.packages.index'));

        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_package_validation_rejects_invalid_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'title' => '',
                'currency' => 'PK',
                'base_price' => -10,
                'duration_days' => 0,
            ])
            ->assertSessionHasErrors(['title', 'currency', 'base_price', 'duration_days']);
    }
}
