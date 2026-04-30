<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IntegrationSupplierAccountService
{
    public function __construct(
        private readonly IntegrationCredentialValidationService $credentialValidation,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): string
    {
        $accountKey = (string) Str::uuid();
        $this->upsertByAccountKey($accountKey, $payload);

        return $accountKey;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertByAccountKey(string $accountKey, array $payload): void
    {
        DB::transaction(function () use ($accountKey, $payload): void {
            foreach (['test', 'production'] as $environment) {
                $envData = is_array($payload[$environment] ?? null) ? $payload[$environment] : [];
                $connection = IntegrationConnection::query()->firstOrNew([
                    'account_key' => $accountKey,
                    'environment' => $environment,
                ]);

                $connection->fill([
                    'account_key' => $accountKey,
                    'name' => (string) $payload['name'],
                    'tenant_id' => $payload['tenant_id'] ?? null,
                    'ownership_type' => (string) ($payload['ownership_type'] ?? 'tenant'),
                    'ownership_tenant_id' => ($payload['ownership_type'] ?? 'tenant') === 'tenant'
                        ? ($payload['ownership_tenant_id'] ?? $payload['tenant_id'] ?? null)
                        : null,
                    'provider' => (string) $payload['provider'],
                    'environment' => $environment,
                    'base_url' => $envData['base_url'] ?? null,
                    'is_active' => (bool) ($envData['is_active'] ?? false),
                    'is_default' => false,
                    'config' => [
                        'managed_via' => 'admin_supplier_accounts',
                    ],
                ]);
                $connection->save();

                $provider = strtolower((string) $payload['provider']);
                $this->upsertCredential($connection, 'client_id', $envData['client_id'] ?? null);
                if ($provider === 'duffel') {
                    $this->upsertCredential(
                        $connection,
                        'api_token',
                        $envData['api_token'] ?? $envData['client_id'] ?? $envData['client_secret'] ?? null
                    );
                } else {
                    $this->upsertCredential($connection, 'client_secret', $envData['client_secret'] ?? null);
                }
            }

            $defaultEnvironment = $payload['default_environment'] ?? null;
            if (is_string($defaultEnvironment) && in_array($defaultEnvironment, ['test', 'production'], true)) {
                $defaultConnection = IntegrationConnection::query()
                    ->where('account_key', $accountKey)
                    ->where('environment', $defaultEnvironment)
                    ->first();

                if ($defaultConnection !== null) {
                    $this->setDefaultConnection($defaultConnection);
                }
            }
        });
    }

    public function deleteByAccountKey(string $accountKey): void
    {
        IntegrationConnection::query()
            ->where('account_key', $accountKey)
            ->delete();
    }

    /**
     * @return Collection<int, IntegrationConnection>
     */
    public function getAccountConnections(string $accountKey): Collection
    {
        return IntegrationConnection::query()
            ->where('account_key', $accountKey)
            ->orderBy('environment')
            ->get();
    }

    public function setDefaultConnection(IntegrationConnection $connection): void
    {
        IntegrationConnection::query()
            ->where('environment', $connection->environment)
            ->where(function ($q) use ($connection): void {
                if ($connection->tenant_id === null) {
                    $q->whereNull('tenant_id');
                } else {
                    $q->where('tenant_id', $connection->tenant_id);
                }
            })
            ->update(['is_default' => false]);

        $connection->forceFill(['is_default' => true])->save();
    }

    private function upsertCredential(IntegrationConnection $connection, string $key, mixed $value): void
    {
        $sanitized = $this->credentialValidation->sanitizeForPersistence([$key => $value]);
        if (! isset($sanitized[$key])) {
            return;
        }

        IntegrationCredential::query()->updateOrCreate(
            [
                'integration_connection_id' => $connection->id,
                'credential_key' => $key,
            ],
            [
                'key_name' => $key,
                'credential_value_encrypted' => $sanitized[$key],
            ]
        );
    }
}
