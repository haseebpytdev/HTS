<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_open_settings_landing_and_sections(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('admin.settings.section', ['section' => 'general']));

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.section', ['section' => 'general']))
            ->assertOk()
            ->assertSee('General Settings');

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.section', ['section' => 'payments']))
            ->assertOk()
            ->assertSee('Payment Settings');
    }

    public function test_super_admin_can_update_section_and_persist_values(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update', ['section' => 'payments']), [
                'settings' => [
                    'payments' => [
                        'default_gateway' => 'manual',
                        'wallet_enabled' => '1',
                        'deposit_min_percent' => '30',
                        'refund_auto_approve_limit' => '5000',
                        'wallet_overdraft_limit' => '10000',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.settings.section', ['section' => 'payments']));

        $this->assertSame('30', ApplicationSetting::getValue('payments.deposit_min_percent'));
        $this->assertSame('5000', ApplicationSetting::getValue('payments.refund_auto_approve_limit'));
        $this->assertSame('10000', ApplicationSetting::getValue('payments.wallet_overdraft_limit'));
    }

}
