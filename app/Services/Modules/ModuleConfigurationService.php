<?php

namespace App\Services\Modules;

use App\Models\IntegrationConnection;
use App\Models\ServiceModule;
use App\Models\ServiceModuleCredential;
use App\Models\ServiceModuleHealthCheck;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Integrations\ConnectionHealthCheckService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ModuleConfigurationService
{
    public function __construct(
        private readonly ComplianceAuditService $audit,
        private readonly ConnectionHealthCheckService $healthCheckService,
    ) {
    }

    /**
     * @param  array<int, string>  $operations
     */
    public function updateStatus(string $moduleKey, bool $isActive, string $environment, bool $isDefault, array $operations, ?int $actorUserId = null): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();

        if ($isDefault) {
            ServiceModule::query()->where('service_type', $module->service_type)->update(['is_default' => false]);
        }

        $status = $isActive ? 'active' : 'inactive';
        if ($isActive && ! $this->hasRequiredCredentials($module->id, $environment)) {
            $status = 'misconfigured';
        }

        $module->forceFill([
            'is_active' => $isActive,
            'environment' => $environment,
            'is_default' => $isDefault,
            'status' => $status,
            'supported_operations_json' => array_values($operations),
        ])->save();

        $this->audit->record(
            area: 'modules',
            action: 'module_status_updated',
            entityType: ServiceModule::class,
            entityId: $module->id,
            severity: 'info',
            context: [
                'is_active' => $isActive,
                'status' => $status,
                'environment' => $environment,
                'is_default' => $isDefault,
                'operations' => array_values($operations),
                'actor_user_id' => $actorUserId,
            ],
        );
    }

    /**
     * @param  array<string, array<string, string|null>>  $credentialsByEnvironment
     */
    public function updateCredentials(string $moduleKey, array $credentialsByEnvironment, ?int $actorUserId = null): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();

        foreach (['sandbox', 'production'] as $environment) {
            $credentials = (array) ($credentialsByEnvironment[$environment] ?? []);
            foreach ($credentials as $key => $value) {
                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                ServiceModuleCredential::query()->updateOrCreate(
                    ['service_module_id' => $module->id, 'credential_key' => $environment.':'.(string) $key],
                    ['credential_value_encrypted' => $value, 'is_secret' => true]
                );
            }
        }

        $this->audit->record(
            area: 'modules',
            action: 'module_credentials_updated',
            entityType: ServiceModule::class,
            entityId: $module->id,
            severity: 'info',
            context: [
                'actor_user_id' => $actorUserId,
                'updated_environments' => array_keys($credentialsByEnvironment),
            ],
        );
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(string $moduleKey, ?int $userId = null): array
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();
        $prefix = $module->environment === 'production' ? 'production' : 'sandbox';
        $isSuccess = false;
        $message = 'Connection test failed.';
        $latencyMs = null;
        $now = now();

        $connection = IntegrationConnection::query()
            ->where('provider', $module->provider ?? $module->provider_code)
            ->where('environment', $prefix)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($connection !== null) {
            $started = microtime(true);
            $result = $this->healthCheckService->check($connection);
            $latencyMs = (int) round((microtime(true) - $started) * 1000);
            $isSuccess = (bool) ($result['ok'] ?? false);
            $message = (string) ($result['message'] ?? $message);
        } else {
            $isSuccess = $this->hasRequiredCredentials($module->id, $module->environment);
            $message = $isSuccess
                ? 'Credentials present for selected environment. No live provider connection mapped.'
                : 'Missing required credentials for selected environment.';
        }

        ServiceModuleHealthCheck::query()->create([
            'service_module_id' => $module->id,
            'result_status' => $isSuccess ? 'healthy' : 'critical',
            'checked_at' => $now,
            'latency_ms' => $latencyMs,
            'message' => $message,
            'raw_response_json' => ['health_score' => $isSuccess ? 95 : 25],
            'created_by_user_id' => $userId,
        ]);

        $module->forceFill([
            'connection_status' => $isSuccess ? 'connected' : 'disconnected',
            'last_tested_at' => $now,
            'last_success_at' => $isSuccess ? $now : $module->last_success_at,
            'last_failure_at' => $isSuccess ? $module->last_failure_at : $now,
            'last_failure_reason' => $isSuccess ? null : $message,
        ])->save();

        $this->audit->record(
            area: 'modules',
            action: 'module_health_check',
            entityType: ServiceModule::class,
            entityId: $module->id,
            severity: $isSuccess ? 'info' : 'warning',
            context: [
                'result' => $isSuccess ? 'healthy' : 'critical',
                'message' => $message,
                'actor_user_id' => $userId,
            ],
        );

        return ['ok' => $isSuccess, 'message' => $message];
    }

    public function validateProviderSpecificRequirements(string $moduleKey, string $environment): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();
        if (! $this->hasRequiredCredentials($module->id, $environment)) {
            throw new RuntimeException('Required provider credentials are missing for selected environment.');
        }
    }

    private function hasRequiredCredentials(int $moduleId, string $environment): bool
    {
        $prefix = in_array($environment, ['production', 'sandbox'], true) ? $environment : 'sandbox';
        $keys = ServiceModuleCredential::query()
            ->where('service_module_id', $moduleId)
            ->where('credential_key', 'like', $prefix.':%')
            ->pluck('credential_key')
            ->all();

        $hasClient = in_array($prefix.':client_id', $keys, true) || in_array($prefix.':api_key', $keys, true);
        $hasSecret = in_array($prefix.':client_secret', $keys, true) || in_array($prefix.':password', $keys, true);

        return $hasClient && $hasSecret;
    }

    public function clearRuleCaches(string $moduleKey): void
    {
        Cache::forget('module.rules.'.$moduleKey);
        Cache::forget('pricing.rules.'.$moduleKey);
        Cache::forget('tax.rules.'.$moduleKey);
    }
}
