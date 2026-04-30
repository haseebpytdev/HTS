<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\TransportType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransportTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureTransportTypesTableExists();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureTransportTypesTableExists(): void
    {
        if (Schema::hasTable('transport_types')) {
            return;
        }

        Schema::create('transport_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_admin_can_list_filter_create_update_and_delete_transport_types(): void
    {
        $admin = $this->admin();

        TransportType::query()->create(['name' => 'Sedan', 'slug' => 'sedan', 'is_active' => true]);
        TransportType::query()->create(['name' => 'Bus', 'slug' => 'bus', 'is_active' => false]);

        $this->actingAs($admin)
            ->get(route('admin.transport-types.index', ['q' => 'Sedan', 'is_active' => 1]))
            ->assertOk()
            ->assertSee('Sedan')
            ->assertDontSee('Bus');

        $this->actingAs($admin)
            ->post(route('admin.transport-types.store'), [
                'name' => 'SUV',
                'description' => 'Premium SUV',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $transportType = TransportType::query()->where('name', 'SUV')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.transport-types.update', $transportType), [
                'name' => 'SUV Updated',
                'description' => 'Updated',
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.transport-types.edit', $transportType));

        $this->assertDatabaseHas('transport_types', [
            'id' => $transportType->id,
            'name' => 'SUV Updated',
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.transport-types.destroy', $transportType))
            ->assertRedirect(route('admin.transport-types.index'));

        $this->assertDatabaseMissing('transport_types', ['id' => $transportType->id]);
    }

    public function test_transport_type_validation_fails_for_invalid_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.transport-types.store'), [
                'name' => '',
            ])
            ->assertSessionHasErrors(['name']);
    }
}
