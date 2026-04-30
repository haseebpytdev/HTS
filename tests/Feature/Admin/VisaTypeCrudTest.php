<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisaType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisaTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureVisaTypesTableExists();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureVisaTypesTableExists(): void
    {
        if (Schema::hasTable('visa_types')) {
            return;
        }

        Schema::create('visa_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('processing_days')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_admin_can_list_filter_create_update_and_delete_visa_types(): void
    {
        $admin = $this->admin();

        VisaType::query()->create([
            'name' => 'Umrah 30 Days',
            'slug' => 'umrah-30-days',
            'processing_days' => 5,
            'is_active' => true,
        ]);
        VisaType::query()->create([
            'name' => 'Tourist 15 Days',
            'slug' => 'tourist-15-days',
            'processing_days' => 3,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.visa-types.index', ['q' => 'Umrah', 'is_active' => 1]))
            ->assertOk()
            ->assertSee('Umrah 30 Days')
            ->assertDontSee('Tourist 15 Days');

        $this->actingAs($admin)
            ->post(route('admin.visa-types.store'), [
                'name' => 'Business Visa',
                'processing_days' => 7,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $visaType = VisaType::query()->where('name', 'Business Visa')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.visa-types.update', $visaType), [
                'name' => 'Business Visa Updated',
                'processing_days' => 10,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.visa-types.edit', $visaType));

        $this->assertDatabaseHas('visa_types', [
            'id' => $visaType->id,
            'name' => 'Business Visa Updated',
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.visa-types.destroy', $visaType))
            ->assertRedirect(route('admin.visa-types.index'));

        $this->assertDatabaseMissing('visa_types', ['id' => $visaType->id]);
    }

    public function test_visa_type_validation_fails_for_invalid_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.visa-types.store'), [
                'name' => '',
                'processing_days' => -1,
            ])
            ->assertSessionHasErrors(['name', 'processing_days']);
    }
}
