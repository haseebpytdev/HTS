<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureApplicationSettingsTableExists();
    }

    private function ensureApplicationSettingsTableExists(): void
    {
        if (Schema::hasTable('application_settings')) {
            return;
        }

        Schema::create('application_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_super_admin_can_open_and_update_settings_module(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Operational Settings');

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'settings' => [
                    'booking' => [
                        'auto_hold_enabled' => '1',
                        'hold_expiry_minutes' => '45',
                    ],
                    'finance' => [
                        'default_markup_percent' => '12.5',
                        'allow_negative_margin' => '0',
                    ],
                    'integrations' => [
                        'default_provider' => 'sabre',
                        'fallback_enabled' => '1',
                    ],
                    'notifications' => [
                        'email_enabled' => '1',
                        'sms_enabled' => '0',
                    ],
                    'tenancy' => [
                        'tenant_scoping_enabled' => '1',
                    ],
                    'app_behavior' => [
                        'quote_auto_save' => '1',
                        'require_booking_approval' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('application_settings', ['key' => 'integrations.default_provider', 'value' => 'sabre']);
        $this->assertDatabaseHas('application_settings', ['key' => 'tenancy.tenant_scoping_enabled', 'value' => '1']);
    }

    public function test_non_super_admin_cannot_access_settings_module(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }
}
