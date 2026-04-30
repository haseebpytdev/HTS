<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmadeusConnectionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_save_amadeus_credentials_from_ui_and_credentials_are_encrypted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.store'), [
            'provider' => AmadeusSelfServiceProvider::CODE,
            'environment' => 'sandbox',
            'account_name' => 'Amadeus Test Connection',
            'base_url' => 'https://test.api.amadeus.com',
            'is_active' => '1',
            'is_default' => '1',
            'supported_operations' => ['search', 'pricing', 'booking'],
            'credentials' => [
                'client_id' => 'amadeus-client-id',
                'client_secret' => 'amadeus-client-secret',
            ],
        ]);

        $response->assertRedirect();

        $connection = IntegrationConnection::query()->firstOrFail();
        $this->assertSame(AmadeusSelfServiceProvider::CODE, $connection->provider);
        $this->assertSame('sandbox', $connection->environment);
        $this->assertTrue($connection->is_active);
        $this->assertTrue($connection->is_default);
        $this->assertSame(['search', 'pricing', 'booking'], $connection->supported_operations);

        $clientId = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'client_id')
            ->firstOrFail();
        $clientSecret = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'client_secret')
            ->firstOrFail();

        $this->assertSame('amadeus-client-id', $clientId->credential_value_encrypted);
        $this->assertSame('amadeus-client-secret', $clientSecret->credential_value_encrypted);

        $rawSecret = DB::table('integration_credentials')
            ->where('id', $clientSecret->id)
            ->value('credential_value_encrypted');
        $this->assertNotSame('amadeus-client-secret', $rawSecret);

        $editPage = $this->actingAs($admin)->get(route('admin.integrations.edit', $connection));
        $editPage->assertOk();
        $editPage->assertDontSee('amadeus-client-id');
        $editPage->assertDontSee('amadeus-client-secret');
        $editPage->assertSee('Stored securely');
    }

    public function test_updating_amadeus_connection_with_blank_secrets_keeps_existing_encrypted_values(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $connection = IntegrationConnection::query()->create([
            'provider' => AmadeusSelfServiceProvider::CODE,
            'environment' => 'sandbox',
            'account_name' => 'Amadeus Test Connection',
            'name' => 'Amadeus Test Connection',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);

        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'existing-client-id',
            'is_secret' => true,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'existing-client-secret',
            'is_secret' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.integrations.update', $connection), [
            'provider' => AmadeusSelfServiceProvider::CODE,
            'environment' => 'sandbox',
            'account_name' => 'Amadeus Test Connection Updated',
            'base_url' => 'https://test.api.amadeus.com',
            'is_active' => '1',
            'is_default' => '0',
            'supported_operations' => ['search', 'pricing', 'booking'],
            'credentials' => [
                'client_id' => '',
                'client_secret' => '',
            ],
        ]);

        $response->assertRedirect();

        $storedClientId = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'client_id')
            ->firstOrFail();
        $storedClientSecret = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'client_secret')
            ->firstOrFail();

        $this->assertSame('existing-client-id', $storedClientId->credential_value_encrypted);
        $this->assertSame('existing-client-secret', $storedClientSecret->credential_value_encrypted);
    }

    public function test_amadeus_test_connection_attempts_oauth_token_path_and_persists_health_fields(): void
    {
        config()->set('integrations.driver', 'amadeus');
        config()->set('integrations.health_checks.force_live_in_tests', true);
        config()->set('amadeus.token_path', '/v1/security/oauth2/token');

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => AmadeusSelfServiceProvider::CODE,
            'environment' => 'sandbox',
            'account_name' => 'Amadeus Test Connection',
            'name' => 'Amadeus Test Connection',
            'base_url' => 'https://test.api.amadeus.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'oauth-client-id',
            'is_secret' => true,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'oauth-client-secret',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://test.api.amadeus.com/v1/security/oauth2/token' => Http::response([
                'access_token' => 'token-value',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.test', $connection));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://test.api.amadeus.com/v1/security/oauth2/token'
                && str_contains((string) $request->body(), 'grant_type=client_credentials')
                && str_contains((string) $request->body(), 'client_id=oauth-client-id')
                && str_contains((string) $request->body(), 'client_secret=oauth-client-secret');
        });

        $connection->refresh();
        $this->assertSame('healthy', $connection->status);
        $this->assertNotNull($connection->last_tested_at);
        $this->assertNotNull($connection->last_success_at);
        $this->assertNull($connection->last_failure_reason);
        $this->assertNotNull($connection->token_expires_at);
    }
}

