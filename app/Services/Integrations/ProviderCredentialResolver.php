<?php

namespace App\Services\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\ResolvedProviderCredentials;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Models\Agency;
use App\Models\IntegrationConnection;
use App\Models\ServiceModule;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use App\Repositories\IntegrationConnectionRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves API credentials from `config/{provider}.php`, with optional overrides from
 * {@see IntegrationConnection} + {@see \App\Models\IntegrationCredential} rows.
 */
final class ProviderCredentialResolver
{
    public function __construct(
        private readonly IntegrationConnectionRepository $connections,
    ) {
    }

    public function forProvider(
        string $providerCode,
        ?int $tenantId = null,
        ?int $agencyId = null,
        ?string $operation = null,
        ?string $runtimeEnvironmentOverride = null
    ): ResolvedProviderCredentials
    {
        $providerCode = AmadeusSelfServiceProvider::normalize($providerCode);
        $resolvedTenantId = $this->resolveTenantId($tenantId, $agencyId);
        $providerConfigKey = $providerCode === AmadeusSelfServiceProvider::CODE ? 'amadeus' : $providerCode;
        $module = $this->moduleForProvider($providerCode, $operation);
        $runtimeEnvironment = $runtimeEnvironmentOverride !== null && $runtimeEnvironmentOverride !== ''
            ? $this->normalizeRuntimeEnvironment($runtimeEnvironmentOverride)
            : $this->runtimeEnvironmentForProvider($providerCode, $module, $resolvedTenantId);
        $env = $runtimeEnvironment === 'sandbox' ? 'test' : 'production';
        $cfg = config($providerConfigKey);
        if (! is_array($cfg)) {
            return new ResolvedProviderCredentials('', '', null, null, null);
        }

        /** @var array<string, mixed> $cfg */
        $credBlock = is_array($cfg['credentials'][$env] ?? null) ? $cfg['credentials'][$env] : [];
        $envBlock = is_array($cfg['environments'][$env] ?? null) ? $cfg['environments'][$env] : [];
        $defaultBase = isset($envBlock['base_url'])
            ? trim((string) $envBlock['base_url'])
            : (isset($cfg['base_url']) ? trim((string) $cfg['base_url']) : '');
        $tokenPath = isset($cfg['token_path']) ? trim((string) $cfg['token_path']) : '';
        $clientId = (string) ($credBlock['client_id'] ?? '');
        $clientSecret = (string) ($credBlock['client_secret'] ?? '');
        if ($providerCode === 'duffel') {
            $token = trim((string) ($credBlock['api_token'] ?? $credBlock['api_key'] ?? $clientId ?? $clientSecret));
            if ($token === '') {
                $token = trim((string) ($envBlock['api_token'] ?? $cfg['api_token'] ?? ''));
            }
            // Duffel uses direct API token model. Normalize token into clientId for provider-aware completeness.
            $clientId = $token;
            $clientSecret = $token;
        }
        if ($providerCode === 'sabre') {
            // Sabre runtime must use stored DB credentials only.
            $clientId = '';
            $clientSecret = '';
            $defaultBase = '';
            $tokenPath = '/v2/auth/token';
        }

        $fromConfig = new ResolvedProviderCredentials(
            clientId: $clientId,
            clientSecret: $clientSecret,
            baseUrl: $defaultBase !== '' ? $defaultBase : null,
            tokenPath: $tokenPath !== '' ? $tokenPath : null,
            credentialSource: 'config',
        );

        $sourcePolicy = $this->credentialSourcePolicyForProvider($providerCode, $module, $operation);
        $useDbCreds = match ($sourcePolicy) {
            'config_only' => false,
            'database_only', 'database_preferred' => true,
            default => (bool) config('integrations.use_database_credentials', false),
        };
        $useDbPersist = (bool) config('integrations.persist_tokens_to_database', false);
        $enforceDbHealth = (bool) config('integrations.enforce_database_connection_health', true);

        if (! $useDbCreds && ! $useDbPersist) {
            $this->logDuffelResolution(
                providerCode: $providerCode,
                operation: $operation,
                runtimeEnvironment: $runtimeEnvironment,
                credentials: $fromConfig,
                source: 'config',
                sourcePolicy: $sourcePolicy,
            );

            return $fromConfig;
        }

        $connection = $this->resolveEligibleConnection($providerCode, $runtimeEnvironment, $resolvedTenantId);
        if ($connection === null) {
            if (($useDbCreds || $sourcePolicy === 'database_only') && $enforceDbHealth) {
                throw $this->unavailable(
                    providerCode: $providerCode,
                    environment: $runtimeEnvironment,
                    code: 'connection_not_eligible',
                    message: 'No active healthy supplier connection is available for the selected provider and environment.',
                );
            }

            $this->logDuffelResolution(
                providerCode: $providerCode,
                operation: $operation,
                runtimeEnvironment: $runtimeEnvironment,
                credentials: $fromConfig,
                source: 'config',
                sourcePolicy: $sourcePolicy,
            );

            return $fromConfig;
        }

        $connectionId = (int) $connection->id;
        $baseUrl = $this->connectionBaseUrl($connection) ?? $fromConfig->baseUrl;

        if ($useDbCreds) {
            $secrets = $this->secretsFromConnection($connection, $providerCode);
            if ($secrets['client_id'] !== '' || $secrets['client_secret'] !== '') {
                $resolved = new ResolvedProviderCredentials(
                    clientId: $secrets['client_id'] !== '' ? $secrets['client_id'] : $fromConfig->clientId,
                    clientSecret: $providerCode === 'duffel'
                        ? ''
                        : ($secrets['client_secret'] !== '' ? $secrets['client_secret'] : $fromConfig->clientSecret),
                    baseUrl: $baseUrl,
                    tokenPath: $fromConfig->tokenPath,
                    integrationConnectionId: $connectionId,
                    credentialSource: 'integration_connection',
                );

                $this->logDuffelResolution(
                    providerCode: $providerCode,
                    operation: $operation,
                    runtimeEnvironment: $runtimeEnvironment,
                    credentials: $resolved,
                    source: 'integration_connection',
                    sourcePolicy: $sourcePolicy,
                );

                return $resolved;
            }
        }

        $fallbackCreds = new ResolvedProviderCredentials(
            clientId: $fromConfig->clientId,
            clientSecret: $providerCode === 'duffel' ? '' : $fromConfig->clientSecret,
            baseUrl: $baseUrl,
            tokenPath: $fromConfig->tokenPath,
            integrationConnectionId: $connectionId,
            credentialSource: $connectionId !== 0 ? 'integration_connection_fallback' : 'config',
        );
        if ($sourcePolicy === 'database_only'
            && ! $this->hasRequiredCredentials($providerCode, $fallbackCreds)
            && $enforceDbHealth
        ) {
            throw $this->unavailable(
                providerCode: $providerCode,
                environment: $runtimeEnvironment,
                code: 'missing_database_credentials',
                message: 'Provider is configured for database-only credentials but required secrets are missing.',
            );
        }

        $this->logDuffelResolution(
            providerCode: $providerCode,
            operation: $operation,
            runtimeEnvironment: $runtimeEnvironment,
            credentials: $fallbackCreds,
            source: $connectionId !== 0 ? 'integration_connection_fallback' : 'config',
            sourcePolicy: $sourcePolicy,
        );

        return $fallbackCreds;
    }

    /**
     * @return array{
     *   token: string,
     *   source: string,
     *   runtime_environment: string,
     *   integration_connection_id: ?int,
     *   token_field: string
     * }
     */
    public function resolveDuffelTokenSelection(
        ?int $tenantId = null,
        ?int $agencyId = null,
        ?string $operation = null,
        ?string $runtimeEnvironmentOverride = null,
        bool $requireIntegrationConnection = false
    ): array {
        $runtimeEnvironment = $runtimeEnvironmentOverride !== null && $runtimeEnvironmentOverride !== ''
            ? $this->normalizeRuntimeEnvironment($runtimeEnvironmentOverride)
            : $this->runtimeEnvironmentForProvider('duffel', $this->moduleForProvider('duffel', $operation), $this->resolveTenantId($tenantId, $agencyId));
        $resolved = $this->forProvider(
            'duffel',
            tenantId: $tenantId,
            agencyId: $agencyId,
            operation: $operation,
            runtimeEnvironmentOverride: $runtimeEnvironment,
        );
        $token = trim($resolved->clientId);
        if ($token === '') {
            $token = trim($resolved->clientSecret);
        }
        $source = $resolved->integrationConnectionId !== null ? 'integration_connection' : 'config';
        if ($requireIntegrationConnection && $source !== 'integration_connection') {
            throw $this->unavailable(
                providerCode: 'duffel',
                environment: $runtimeEnvironment,
                code: 'missing_integration_connection_credentials',
                message: 'Duffel search requires active integration-connection credentials for runtime execution.',
            );
        }

        return [
            'token' => $token,
            'source' => $source,
            'runtime_environment' => $runtimeEnvironment,
            'integration_connection_id' => $resolved->integrationConnectionId,
            'token_field' => 'api_token',
        ];
    }

    /**
     * @return array{
     *   token: string,
     *   source: string,
     *   runtime_environment: string,
     *   integration_connection_id: int,
     *   token_field: string
     * }
     */
    public function resolveDuffelTokenForConnection(IntegrationConnection $connection): array
    {
        $connection->loadMissing('credentials');
        $values = [];
        foreach ($connection->credentials as $credential) {
            $key = strtolower((string) $credential->credential_key);
            if ($key === '') {
                continue;
            }

            $values[$key] = (string) $credential->credential_value_encrypted;
        }

        [$token, $tokenField] = $this->extractDuffelToken($values);

        return [
            'token' => $token,
            'source' => 'integration_connection',
            'runtime_environment' => $this->normalizeRuntimeEnvironment((string) ($connection->environment ?: 'sandbox')),
            'integration_connection_id' => (int) $connection->id,
            'token_field' => $tokenField,
        ];
    }

    private function unavailable(string $providerCode, string $environment, string $code, string $message): SupplierIntegrationException
    {
        if ($providerCode === 'duffel') {
            Log::error('integrations.duffel.resolver.unavailable', [
                'provider' => $providerCode,
                'environment' => $environment,
                'availability_code' => $code,
                'message' => $message,
            ]);
        }

        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'INTEGRATION_CONNECTION_UNAVAILABLE',
            normalizedCode: 'integration_provider_unavailable',
            supplierContext: [
                'provider' => $providerCode,
                'environment' => $environment,
                'availability_code' => $code,
            ],
            apiError: new ApiErrorData(
                code: 'integration_provider_unavailable',
                message: $message,
                supplierCode: 'INTEGRATION_CONNECTION_UNAVAILABLE',
                httpStatus: 503,
            ),
        );
    }

    private function credentialEnvironment(): string
    {
        $env = (string) config('integrations.credential_environment', 'production');

        return in_array($env, ['test', 'production'], true) ? $env : 'production';
    }

    private function runtimeEnvironmentForProvider(string $providerCode, ?ServiceModule $module = null, ?int $tenantId = null): string
    {
        if ($tenantId !== null && Schema::hasTable('tenant_provider_access') && Schema::hasColumn('tenant_provider_access', 'environment')) {
            $query = TenantProviderAccess::query()
                ->where('tenant_id', $tenantId)
                ->when(
                    AmadeusSelfServiceProvider::matches($providerCode),
                    static fn ($q) => $q->whereIn('provider', AmadeusSelfServiceProvider::aliases()),
                    static fn ($q) => $q->where('provider', $providerCode)
                )
                ->orderBy('priority_order');

            /** @var TenantProviderAccess|null $tenantOverride */
            $tenantOverride = $query->first();
            $overrideEnvironment = strtolower((string) ($tenantOverride?->environment ?? ''));
            if (in_array($overrideEnvironment, ['development', 'test', 'testing', 'sandbox'], true)) {
                return 'sandbox';
            }
            if (in_array($overrideEnvironment, ['production', 'live'], true)) {
                return 'production';
            }
        }

        $moduleConfig = is_array($module?->config) ? $module->config : [];
        $moduleIntegrationEnv = strtolower((string) ($moduleConfig['integration_env'] ?? ''));
        if (in_array($moduleIntegrationEnv, ['development', 'test', 'testing', 'sandbox'], true)) {
            return 'sandbox';
        }
        if (in_array($moduleIntegrationEnv, ['production', 'live'], true)) {
            return 'production';
        }

        $moduleEnvironment = $module?->environment;

        $normalized = strtolower((string) $moduleEnvironment);
        if (in_array($normalized, ['development', 'test', 'testing', 'sandbox'], true)) {
            return 'sandbox';
        }
        if (in_array($normalized, ['production', 'live'], true)) {
            return 'production';
        }
        if (in_array($normalized, ['sandbox', 'production'], true)) {
            return $normalized;
        }

        return $this->credentialEnvironment() === 'test' ? 'sandbox' : 'production';
    }

    private function normalizeRuntimeEnvironment(string $environment): string
    {
        $normalized = strtolower(trim($environment));

        return in_array($normalized, ['sandbox', 'test', 'testing', 'development'], true)
            ? 'sandbox'
            : 'production';
    }

    private function credentialSourcePolicyForProvider(string $providerCode, ?ServiceModule $module = null, ?string $operation = null): string
    {
        $rawConfig = $module?->config;
        $config = is_array($rawConfig) ? $rawConfig : (is_string($rawConfig) ? (json_decode($rawConfig, true) ?: []) : []);
        $policy = strtolower((string) (($config['credential_source'] ?? '') ?: ''));
        if (in_array($policy, ['config_only', 'database_only', 'database_preferred'], true)) {
            return $policy;
        }

        if ($providerCode === 'duffel' && in_array($operation, ['search', 'pricing', 'booking', null], true)) {
            return 'database_preferred';
        }
        if ($providerCode === 'sabre' && in_array($operation, ['search', 'pricing', 'booking', null], true)) {
            return 'database_only';
        }

        return 'default';
    }

    private function connectionBaseUrl(IntegrationConnection $connection): ?string
    {
        $url = $connection->base_url;

        return $url !== null && $url !== '' ? $url : null;
    }

    /**
     * @return array{client_id: string, client_secret: string}
     */
    private function secretsFromConnection(IntegrationConnection $connection, string $providerCode): array
    {
        $connection->loadMissing('credentials');

        $out = ['client_id' => '', 'client_secret' => ''];
        foreach ($connection->credentials as $cred) {
            $key = strtolower((string) $cred->credential_key);
            if ($key === 'client_id') {
                $out['client_id'] = (string) $cred->credential_value_encrypted;
            }
            if ($key === 'client_secret') {
                $out['client_secret'] = (string) $cred->credential_value_encrypted;
            }
            if ($providerCode === 'sabre') {
                if (in_array($key, ['username', 'user_id', 'userid', 'sabre_user_id'], true)) {
                    $out['client_id'] = (string) $cred->credential_value_encrypted;
                }
                if (in_array($key, ['password', 'passcode', 'sabre_password'], true)) {
                    $out['client_secret'] = (string) $cred->credential_value_encrypted;
                }
            }
            if ($providerCode === 'duffel' && in_array($key, ['api_token', 'api_key', 'client_id', 'client_secret'], true)) {
                $token = (string) $cred->credential_value_encrypted;
                $out['client_id'] = $token;
                $out['client_secret'] = $token;
            }
        }

        return $out;
    }

    private function resolveTenantId(?int $tenantId, ?int $agencyId): ?int
    {
        if ($tenantId !== null) {
            return $tenantId;
        }
        if ($agencyId !== null) {
            $resolved = Agency::query()->whereKey($agencyId)->value('tenant_id');

            return $resolved !== null ? (int) $resolved : null;
        }

        return Tenant::defaultId();
    }

    private function moduleForProvider(string $providerCode, ?string $operation = null): ?ServiceModule
    {
        if (! Schema::hasTable('service_modules')) {
            return null;
        }

        $query = ServiceModule::query()
            ->where('service_type', 'Flights')
            ->where(function ($q) use ($providerCode): void {
                if (AmadeusSelfServiceProvider::matches($providerCode)) {
                    $q->whereIn('provider', AmadeusSelfServiceProvider::aliases())
                        ->orWhereIn('provider_code', AmadeusSelfServiceProvider::aliases());
                } else {
                    $q->where('provider', $providerCode)
                        ->orWhere('provider_code', $providerCode);
                }
            })
            ->where('is_active', true)
            ->whereNotIn('status', ['inactive', 'misconfigured'])
            ->orderByDesc('is_default')
            ->orderBy('provider_priority')
            ->orderBy('sort_order');

        /** @var ServiceModule|null $candidate */
        $candidate = $query->first();
        if ($candidate === null || $operation === null) {
            return $candidate;
        }

        $ops = (array) ($candidate->supported_operations_json ?: $candidate->available_operations ?: []);
        if (in_array($operation, $ops, true)) {
            return $candidate;
        }

        return null;
    }

    private function resolveEligibleConnection(string $providerCode, string $runtimeEnvironment, ?int $tenantId): ?IntegrationConnection
    {
        if (! Schema::hasTable('integration_connections')) {
            return null;
        }

        if ($providerCode === 'sabre') {
            $environmentAliases = $runtimeEnvironment === 'sandbox'
                ? ['sandbox', 'test', 'testing', 'development']
                : ['production', 'live'];

            $query = IntegrationConnection::query()
                ->where('provider', 'sabre')
                ->whereIn('environment', $environmentAliases)
                ->where('is_active', true)
                ->where(function ($q) use ($tenantId): void {
                    if ($tenantId !== null) {
                        $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                    } else {
                        $q->whereNull('tenant_id');
                    }
                })
                ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('is_default')
                ->orderByDesc('id');

            /** @var IntegrationConnection|null $sabreConnection */
            $sabreConnection = $query->first();
            if ($sabreConnection !== null) {
                return $sabreConnection;
            }
        }

        $connection = $this->connections->findActiveForTenantProviderAndEnvironment(
            provider: $providerCode,
            environment: $runtimeEnvironment,
            tenantId: $tenantId
        );
        if ($connection !== null) {
            return $connection;
        }

        return $this->connections->findActiveForProviderAndEnvironment(
            $providerCode,
            $runtimeEnvironment === 'sandbox' ? 'test' : $runtimeEnvironment
        );
    }

    private function hasRequiredCredentials(string $providerCode, ResolvedProviderCredentials $credentials): bool
    {
        if ($providerCode === 'duffel') {
            return $credentials->clientId !== '';
        }

        return $credentials->clientId !== '' && $credentials->clientSecret !== '';
    }

    /**
     * @param  array<string, string>  $values
     * @return array{0: string, 1: string}
     */
    private function extractDuffelToken(array $values): array
    {
        foreach (['api_token', 'api_key', 'client_id', 'client_secret'] as $field) {
            $token = trim((string) ($values[$field] ?? ''));
            if ($token !== '') {
                return [$token, $field];
            }
        }

        return ['', 'api_token'];
    }

    private function logDuffelResolution(
        string $providerCode,
        ?string $operation,
        string $runtimeEnvironment,
        ResolvedProviderCredentials $credentials,
        string $source,
        string $sourcePolicy,
    ): void {
        if ($providerCode !== 'duffel' || ! $this->shouldLogDuffelDebug()) {
            return;
        }

        $token = trim($credentials->clientId);
        Log::info('integrations.duffel.resolver.credentials_resolved', [
            'provider' => $providerCode,
            'operation' => $operation ?? 'default',
            'runtime_environment' => $runtimeEnvironment,
            'credential_source' => $source,
            'credential_source_policy' => $sourcePolicy,
            'integration_connection_id' => $credentials->integrationConnectionId,
            'token_present' => $token !== '',
            'token_prefix' => $this->maskTokenPrefix($token),
            'authorization_scheme' => 'Bearer',
        ]);
    }

    private function shouldLogDuffelDebug(): bool
    {
        return (bool) config('app.debug', false) || (bool) config('integrations.debug_duffel_auth', false);
    }

    private function maskTokenPrefix(string $token): string
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return 'missing';
        }

        return substr($trimmed, 0, min(strlen($trimmed), 12)).'...';
    }
}
