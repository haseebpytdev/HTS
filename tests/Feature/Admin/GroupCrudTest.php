<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureGroupTablesExist();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureGroupTablesExist(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('destinations')) {
            Schema::create('destinations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('destination_id')->nullable();
                $table->string('title');
                $table->string('slug')->unique();
                $table->decimal('base_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('PKR');
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('group_type')->nullable();
                $table->date('departure_date')->nullable();
                $table->date('return_date')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedInteger('seats_left')->nullable();
                $table->string('status')->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_list_filter_create_update_and_delete_groups(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'umrah']);
        $destination = Destination::query()->create(['name' => 'Makkah', 'slug' => 'makkah']);
        $package = TravelPackage::query()->create([
            'title' => 'Gold Package',
            'slug' => 'gold-package',
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'base_price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        TravelGroup::query()->create([
            'package_id' => $package->id,
            'name' => 'Featured Group',
            'slug' => 'featured-group',
            'departure_date' => '2027-01-10',
            'return_date' => '2027-01-20',
            'capacity' => 40,
            'seats_left' => 25,
            'status' => 'featured',
        ]);
        TravelGroup::query()->create([
            'package_id' => $package->id,
            'name' => 'Closed Group',
            'slug' => 'closed-group',
            'capacity' => 20,
            'seats_left' => 0,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.groups.index', [
                'q' => 'Featured',
                'destination_id' => $destination->id,
                'is_featured' => 1,
                'is_active' => 1,
            ]))
            ->assertOk()
            ->assertSee('Featured Group')
            ->assertDontSee('Closed Group');

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'package_id' => $package->id,
                'name' => 'Operations Group',
                'departure_date' => '2027-02-01',
                'return_date' => '2027-02-10',
                'capacity' => 50,
                'seats_left' => 40,
                'pricing_tiers' => 'Tier A',
                'airline_info' => 'PIA',
                'hotel_info' => 'Hilton',
                'notes' => 'Ops note',
                'is_featured' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $group = TravelGroup::query()->where('name', 'Operations Group')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.groups.update', $group), [
                'package_id' => $package->id,
                'name' => 'Operations Group Updated',
                'departure_date' => '2027-02-05',
                'return_date' => '2027-02-15',
                'capacity' => 60,
                'seats_left' => 45,
                'pricing_tiers' => 'Tier B',
                'airline_info' => 'Saudia',
                'hotel_info' => 'Fairmont',
                'notes' => 'Updated note',
                'is_featured' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.groups.edit', $group));

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Operations Group Updated',
            'status' => 'featured',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.groups.destroy', $group))
            ->assertRedirect(route('admin.groups.index'));

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_group_validation_rejects_invalid_dates_and_seat_counts(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat']);
        $destination = Destination::query()->create(['name' => 'Dest', 'slug' => 'dest']);
        $package = TravelPackage::query()->create([
            'title' => 'Pkg',
            'slug' => 'pkg-'.uniqid(),
            'category_id' => $category->id,
            'destination_id' => $destination->id,
            'base_price' => 1000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'package_id' => $package->id,
                'name' => '',
                'departure_date' => '2027-10-10',
                'return_date' => '2027-09-10',
                'capacity' => 0,
                'seats_left' => -1,
            ])
            ->assertSessionHasErrors(['name', 'return_date', 'capacity', 'seats_left']);
    }
}
