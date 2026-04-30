<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotelRoomTypeCrudTest extends TestCase
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
                $table->string('city')->nullable();
                $table->text('address')->nullable();
                $table->unsignedTinyInteger('star_rating')->nullable();
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
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_list_and_filter_room_types(): void
    {
        $admin = $this->admin();
        $hotelA = Hotel::query()->create(['name' => 'A Hotel', 'slug' => 'a-hotel', 'is_active' => true]);
        $hotelB = Hotel::query()->create(['name' => 'B Hotel', 'slug' => 'b-hotel', 'is_active' => true]);

        HotelRoomType::query()->create([
            'hotel_id' => $hotelA->id,
            'name' => 'Double Deluxe',
            'max_adults' => 2,
            'base_capacity' => 2,
            'is_active' => true,
        ]);
        HotelRoomType::query()->create([
            'hotel_id' => $hotelB->id,
            'name' => 'Single Compact',
            'max_adults' => 1,
            'base_capacity' => 1,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hotel-room-types.index', ['hotel_id' => $hotelA->id, 'sharing_basis' => 'double']))
            ->assertOk()
            ->assertSee('Double Deluxe')
            ->assertDontSee('Single Compact');
    }

    public function test_admin_can_create_update_and_delete_room_type(): void
    {
        $admin = $this->admin();
        $hotel = Hotel::query()->create(['name' => 'A Hotel', 'slug' => 'a-hotel', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.hotel-room-types.store'), [
                'hotel_id' => $hotel->id,
                'name' => 'Triple Family',
                'sharing_basis' => 'triple',
                'max_children' => 2,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $roomType = HotelRoomType::query()->where('name', 'Triple Family')->firstOrFail();
        $this->assertSame(3, (int) $roomType->base_capacity);

        $this->actingAs($admin)
            ->put(route('admin.hotel-room-types.update', $roomType), [
                'hotel_id' => $hotel->id,
                'name' => 'Double Family',
                'sharing_basis' => 'double',
                'max_children' => 1,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.hotel-room-types.edit', $roomType));

        $this->assertDatabaseHas('hotel_room_types', [
            'id' => $roomType->id,
            'name' => 'Double Family',
            'base_capacity' => 2,
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.hotel-room-types.destroy', $roomType))
            ->assertRedirect(route('admin.hotel-room-types.index'));

        $this->assertDatabaseMissing('hotel_room_types', ['id' => $roomType->id]);
    }

    public function test_room_type_validation_rejects_invalid_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.hotel-room-types.store'), [
                'name' => '',
                'sharing_basis' => 'invalid-basis',
                'hotel_id' => 999999,
            ])
            ->assertSessionHasErrors(['name', 'sharing_basis', 'hotel_id']);
    }
}
