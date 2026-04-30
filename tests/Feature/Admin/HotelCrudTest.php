<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotelCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureHotelsTableExists();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);
    }

    private function ensureHotelsTableExists(): void
    {
        if (Schema::hasTable('hotels')) {
            return;
        }

        Schema::create('hotels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->decimal('distance_from_haram_km', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_admin_can_list_and_filter_hotels(): void
    {
        $admin = $this->admin();
        Hotel::query()->create([
            'name' => 'Makkah Tower Hotel',
            'slug' => 'makkah-tower-hotel',
            'city' => 'Makkah',
            'address' => 'Ibrahim Khalil Rd',
            'star_rating' => 5,
            'is_active' => true,
        ]);
        Hotel::query()->create([
            'name' => 'Madinah Stay',
            'slug' => 'madinah-stay',
            'city' => 'Madinah',
            'address' => 'Central Area',
            'star_rating' => 4,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hotels.index', ['city' => 'Makkah', 'is_active' => 1]))
            ->assertOk()
            ->assertSee('Makkah Tower Hotel')
            ->assertDontSee('Madinah Stay');
    }

    public function test_admin_can_create_hotel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.hotels.store'), [
                'name' => 'Clock Royal',
                'city' => 'Makkah',
                'address' => 'Abraj Clock Tower',
                'star_rating' => 5,
                'distance_from_haram_km' => 0.20,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hotels', [
            'name' => 'Clock Royal',
            'city' => 'Makkah',
            'star_rating' => 5,
            'is_active' => 1,
        ]);
    }

    public function test_hotel_store_validation_fails_for_missing_name(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.hotels.store'), [
                'name' => '',
                'star_rating' => 10,
            ])
            ->assertSessionHasErrors(['name', 'star_rating']);
    }

    public function test_admin_can_update_and_delete_hotel(): void
    {
        $admin = $this->admin();
        $hotel = Hotel::query()->create([
            'name' => 'Initial Hotel',
            'slug' => 'initial-hotel',
            'city' => 'Makkah',
            'address' => 'Old address',
            'star_rating' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.hotels.update', $hotel), [
                'name' => 'Updated Hotel',
                'city' => 'Madinah',
                'address' => 'Updated address',
                'star_rating' => 4,
                'distance_from_haram_km' => 0.70,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.hotels.edit', $hotel));

        $this->assertDatabaseHas('hotels', [
            'id' => $hotel->id,
            'name' => 'Updated Hotel',
            'city' => 'Madinah',
            'star_rating' => 4,
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.hotels.destroy', $hotel))
            ->assertRedirect(route('admin.hotels.index'));

        $this->assertDatabaseMissing('hotels', ['id' => $hotel->id]);
    }
}
