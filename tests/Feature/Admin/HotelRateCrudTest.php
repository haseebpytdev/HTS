<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\HotelRate;
use App\Models\HotelRoomType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotelRateCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureInventoryTablesExist();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureInventoryTablesExist(): void
    {
        if (! Schema::hasTable('hotels')) {
            Schema::create('hotels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hotel_room_types')) {
            Schema::create('hotel_room_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedTinyInteger('max_adults')->default(2);
                $table->unsignedTinyInteger('max_children')->default(0);
                $table->unsignedSmallInteger('base_capacity')->default(2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hotel_rates')) {
            Schema::create('hotel_rates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hotel_room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('meal_plan')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('rate_per_night', 12, 2);
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_list_and_filter_hotel_rates(): void
    {
        $admin = $this->admin();
        $hotel = Hotel::query()->create(['name' => 'Makkah Stay', 'slug' => 'makkah-stay', 'is_active' => true]);
        $roomType = HotelRoomType::query()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Double',
            'max_adults' => 2,
            'base_capacity' => 2,
            'is_active' => true,
        ]);

        HotelRate::query()->create([
            'hotel_room_type_id' => $roomType->id,
            'meal_plan' => 'Ramadan 2027',
            'currency' => 'PKR',
            'rate_per_night' => 15000,
            'valid_from' => '2027-02-01',
            'valid_to' => '2027-03-10',
            'is_active' => true,
        ]);
        HotelRate::query()->create([
            'hotel_room_type_id' => $roomType->id,
            'meal_plan' => 'Off Season',
            'currency' => 'PKR',
            'rate_per_night' => 9000,
            'valid_from' => '2027-04-01',
            'valid_to' => '2027-06-10',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hotel-rates.index', [
                'hotel_id' => $hotel->id,
                'season_name' => 'Ramadan',
                'is_active' => 1,
                'valid_from' => '2027-01-01',
                'valid_to' => '2027-03-31',
            ]))
            ->assertOk()
            ->assertSee('Ramadan 2027')
            ->assertDontSee('Off Season');
    }

    public function test_admin_can_create_update_and_delete_hotel_rate(): void
    {
        $admin = $this->admin();
        $hotel = Hotel::query()->create(['name' => 'Madinah View', 'slug' => 'madinah-view', 'is_active' => true]);
        $roomType = HotelRoomType::query()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Triple',
            'max_adults' => 3,
            'base_capacity' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hotel-rates.store'), [
                'hotel_room_type_id' => $roomType->id,
                'season_name' => 'Peak',
                'valid_from' => '2027-01-01',
                'valid_to' => '2027-02-01',
                'currency' => 'pkr',
                'rate_per_night' => 12500.50,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $rate = HotelRate::query()->where('meal_plan', 'Peak')->firstOrFail();
        $this->assertSame('PKR', $rate->currency);

        $this->actingAs($admin)
            ->put(route('admin.hotel-rates.update', $rate), [
                'hotel_room_type_id' => $roomType->id,
                'season_name' => 'Shoulder',
                'valid_from' => '2027-02-05',
                'valid_to' => '2027-03-01',
                'currency' => 'SAR',
                'rate_per_night' => 13000,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.hotel-rates.edit', $rate));

        $this->assertDatabaseHas('hotel_rates', [
            'id' => $rate->id,
            'meal_plan' => 'Shoulder',
            'currency' => 'SAR',
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.hotel-rates.destroy', $rate))
            ->assertRedirect(route('admin.hotel-rates.index'));

        $this->assertDatabaseMissing('hotel_rates', ['id' => $rate->id]);
    }

    public function test_hotel_rate_validation_rejects_invalid_date_range_and_numeric(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.hotel-rates.store'), [
                'hotel_room_type_id' => 999999,
                'season_name' => '',
                'valid_from' => '2027-03-10',
                'valid_to' => '2027-02-01',
                'currency' => 'PK',
                'rate_per_night' => -10,
            ])
            ->assertSessionHasErrors([
                'hotel_room_type_id',
                'season_name',
                'valid_to',
                'currency',
                'rate_per_night',
            ]);
    }
}
