<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_PROVIDER = 'amadeus';
    private const CANONICAL_PROVIDER = 'amadeus_self_service';

    public function up(): void
    {
        $this->backfillIntegrationConnections();
        $this->backfillServiceModules();
        $this->backfillTenantProviderAccess();
        $this->backfillTenantIntegrationPolicies();
        $this->backfillApplicationSettings();
        $this->backfillSettingsTable();
    }

    public function down(): void
    {
        $this->revertIntegrationConnections();
        $this->revertServiceModules();
        $this->revertTenantProviderAccess();
        $this->revertTenantIntegrationPolicies();
        $this->revertApplicationSettings();
        $this->revertSettingsTable();
    }

    private function backfillIntegrationConnections(): void
    {
        if (! Schema::hasTable('integration_connections') || ! Schema::hasColumn('integration_connections', 'provider')) {
            return;
        }

        DB::table('integration_connections')
            ->where('provider', self::LEGACY_PROVIDER)
            ->update(['provider' => self::CANONICAL_PROVIDER]);
    }

    private function backfillServiceModules(): void
    {
        if (! Schema::hasTable('service_modules')) {
            return;
        }

        $hasProvider = Schema::hasColumn('service_modules', 'provider');
        $hasProviderCode = Schema::hasColumn('service_modules', 'provider_code');
        $hasConfig = Schema::hasColumn('service_modules', 'config');
        if (! $hasProvider && ! $hasProviderCode) {
            return;
        }

        $query = DB::table('service_modules')->select(['id']);
        if ($hasProvider && $hasProviderCode) {
            $query->where(function ($w): void {
                $w->where('provider', self::LEGACY_PROVIDER)
                    ->orWhere('provider_code', self::LEGACY_PROVIDER);
            });
        } elseif ($hasProvider) {
            $query->where('provider', self::LEGACY_PROVIDER);
        } else {
            $query->where('provider_code', self::LEGACY_PROVIDER);
        }

        $rows = $query->get();
        foreach ($rows as $row) {
            $payload = [];
            if ($hasProvider) {
                $payload['provider'] = self::CANONICAL_PROVIDER;
            }
            if ($hasProviderCode) {
                $payload['provider_code'] = self::CANONICAL_PROVIDER;
            }
            if (Schema::hasColumn('service_modules', 'provider_name')) {
                $payload['provider_name'] = 'Amadeus Self Service';
            }
            if (Schema::hasColumn('service_modules', 'name')) {
                $payload['name'] = 'Amadeus Self Service';
            }

            if ($hasConfig) {
                $existingConfig = DB::table('service_modules')->where('id', $row->id)->value('config');
                $decoded = is_string($existingConfig) ? json_decode($existingConfig, true) : [];
                if (! is_array($decoded)) {
                    $decoded = [];
                }
                $decoded['is_experimental'] = true;
                $decoded['provider_type'] = 'sandbox';
                $payload['config'] = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            }

            DB::table('service_modules')->where('id', $row->id)->update($payload);
        }
    }

    private function backfillTenantProviderAccess(): void
    {
        if (! Schema::hasTable('tenant_provider_access')) {
            return;
        }

        $rows = DB::table('tenant_provider_access')
            ->whereIn('provider', [self::LEGACY_PROVIDER, self::CANONICAL_PROVIDER])
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $tenantId = (int) $row->tenant_id;
            if (! isset($grouped[$tenantId])) {
                $grouped[$tenantId] = ['legacy' => null, 'canonical' => null];
            }
            if ((string) $row->provider === self::CANONICAL_PROVIDER) {
                $grouped[$tenantId]['canonical'] = $row;
            } else {
                $grouped[$tenantId]['legacy'] = $row;
            }
        }

        foreach ($grouped as $bucket) {
            $legacy = $bucket['legacy'];
            $canonical = $bucket['canonical'];

            if ($legacy === null) {
                continue;
            }

            if ($canonical === null) {
                DB::table('tenant_provider_access')
                    ->where('id', $legacy->id)
                    ->update(['provider' => self::CANONICAL_PROVIDER]);
                continue;
            }

            $merged = [
                'can_search' => (bool) $canonical->can_search || (bool) $legacy->can_search,
                'can_price' => (bool) $canonical->can_price || (bool) $legacy->can_price,
                'can_book' => (bool) $canonical->can_book || (bool) $legacy->can_book,
                'allow_multi_provider' => (bool) $canonical->allow_multi_provider || (bool) $legacy->allow_multi_provider,
                'allow_fallback' => (bool) $canonical->allow_fallback || (bool) $legacy->allow_fallback,
                'is_enabled' => (bool) $canonical->is_enabled || (bool) $legacy->is_enabled,
                'priority_order' => min((int) $canonical->priority_order, (int) $legacy->priority_order),
            ];

            if (Schema::hasColumn('tenant_provider_access', 'usage_quota_json')) {
                $legacyQuota = is_string($legacy->usage_quota_json) ? json_decode($legacy->usage_quota_json, true) : (array) $legacy->usage_quota_json;
                $canonicalQuota = is_string($canonical->usage_quota_json) ? json_decode($canonical->usage_quota_json, true) : (array) $canonical->usage_quota_json;
                if (! is_array($legacyQuota)) {
                    $legacyQuota = [];
                }
                if (! is_array($canonicalQuota)) {
                    $canonicalQuota = [];
                }
                $merged['usage_quota_json'] = json_encode(array_merge($legacyQuota, $canonicalQuota), JSON_UNESCAPED_SLASHES);
            }

            if (Schema::hasColumn('tenant_provider_access', 'soft_limit_percent')) {
                $merged['soft_limit_percent'] = max((int) ($legacy->soft_limit_percent ?? 0), (int) ($canonical->soft_limit_percent ?? 0));
            }
            if (Schema::hasColumn('tenant_provider_access', 'hard_limit_enforced')) {
                $merged['hard_limit_enforced'] = (bool) ($legacy->hard_limit_enforced ?? false) || (bool) ($canonical->hard_limit_enforced ?? false);
            }
            if (Schema::hasColumn('tenant_provider_access', 'overage_alert_enabled')) {
                $merged['overage_alert_enabled'] = (bool) ($legacy->overage_alert_enabled ?? false) || (bool) ($canonical->overage_alert_enabled ?? false);
            }

            DB::table('tenant_provider_access')
                ->where('id', $canonical->id)
                ->update($merged);
            DB::table('tenant_provider_access')
                ->where('id', $legacy->id)
                ->delete();
        }
    }

    private function backfillTenantIntegrationPolicies(): void
    {
        if (! Schema::hasTable('tenant_integration_policies')) {
            return;
        }

        $rows = DB::table('tenant_integration_policies')
            ->select(['id', 'allowed_providers', 'provider_priority'])
            ->get();

        foreach ($rows as $row) {
            $allowed = $this->decodeStringArray($row->allowed_providers);
            $priority = $this->decodeStringArray($row->provider_priority);
            $normalizedAllowed = $this->normalizeProviderArray($allowed);
            $normalizedPriority = $this->normalizeProviderArray($priority);

            DB::table('tenant_integration_policies')
                ->where('id', $row->id)
                ->update([
                    'allowed_providers' => json_encode($normalizedAllowed, JSON_UNESCAPED_SLASHES),
                    'provider_priority' => json_encode($normalizedPriority, JSON_UNESCAPED_SLASHES),
                ]);
        }
    }

    private function backfillApplicationSettings(): void
    {
        if (! Schema::hasTable('application_settings')) {
            return;
        }

        if (Schema::hasColumn('application_settings', 'key') && Schema::hasColumn('application_settings', 'value')) {
            DB::table('application_settings')
                ->where('key', 'integrations.default_provider')
                ->where('value', self::LEGACY_PROVIDER)
                ->update(['value' => self::CANONICAL_PROVIDER]);
        }

        if (! Schema::hasColumn('application_settings', 'provider')) {
            return;
        }

        $rows = DB::table('application_settings')
            ->where('provider', self::LEGACY_PROVIDER)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $duplicateQuery = DB::table('application_settings')
                ->where('scope', $row->scope)
                ->where('key', $row->key)
                ->where('provider', self::CANONICAL_PROVIDER);

            if (Schema::hasColumn('application_settings', 'scope_id')) {
                if ($row->scope_id === null) {
                    $duplicateQuery->whereNull('scope_id');
                } else {
                    $duplicateQuery->where('scope_id', $row->scope_id);
                }
            }
            if (Schema::hasColumn('application_settings', 'module')) {
                if ($row->module === null) {
                    $duplicateQuery->whereNull('module');
                } else {
                    $duplicateQuery->where('module', $row->module);
                }
            }
            if (Schema::hasColumn('application_settings', 'category')) {
                if ($row->category === null) {
                    $duplicateQuery->whereNull('category');
                } else {
                    $duplicateQuery->where('category', $row->category);
                }
            }

            $hasDuplicate = $duplicateQuery->exists();
            if ($hasDuplicate) {
                DB::table('application_settings')->where('id', $row->id)->delete();
                continue;
            }

            DB::table('application_settings')
                ->where('id', $row->id)
                ->update(['provider' => self::CANONICAL_PROVIDER]);
        }
    }

    private function backfillSettingsTable(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (! Schema::hasColumn('settings', 'key') || ! Schema::hasColumn('settings', 'value')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'integrations.default_provider')
            ->where('value', self::LEGACY_PROVIDER)
            ->update(['value' => self::CANONICAL_PROVIDER]);
    }

    private function revertIntegrationConnections(): void
    {
        if (! Schema::hasTable('integration_connections') || ! Schema::hasColumn('integration_connections', 'provider')) {
            return;
        }

        DB::table('integration_connections')
            ->where('provider', self::CANONICAL_PROVIDER)
            ->update(['provider' => self::LEGACY_PROVIDER]);
    }

    private function revertServiceModules(): void
    {
        if (! Schema::hasTable('service_modules')) {
            return;
        }

        if (Schema::hasColumn('service_modules', 'provider')) {
            DB::table('service_modules')
                ->where('provider', self::CANONICAL_PROVIDER)
                ->update(['provider' => self::LEGACY_PROVIDER]);
        }

        if (Schema::hasColumn('service_modules', 'provider_code')) {
            DB::table('service_modules')
                ->where('provider_code', self::CANONICAL_PROVIDER)
                ->update(['provider_code' => self::LEGACY_PROVIDER]);
        }
    }

    private function revertTenantProviderAccess(): void
    {
        if (! Schema::hasTable('tenant_provider_access') || ! Schema::hasColumn('tenant_provider_access', 'provider')) {
            return;
        }

        DB::table('tenant_provider_access')
            ->where('provider', self::CANONICAL_PROVIDER)
            ->update(['provider' => self::LEGACY_PROVIDER]);
    }

    private function revertTenantIntegrationPolicies(): void
    {
        if (! Schema::hasTable('tenant_integration_policies')) {
            return;
        }

        $rows = DB::table('tenant_integration_policies')
            ->select(['id', 'allowed_providers', 'provider_priority'])
            ->get();

        foreach ($rows as $row) {
            $allowed = $this->decodeStringArray($row->allowed_providers);
            $priority = $this->decodeStringArray($row->provider_priority);

            DB::table('tenant_integration_policies')
                ->where('id', $row->id)
                ->update([
                    'allowed_providers' => json_encode($this->denormalizeProviderArray($allowed), JSON_UNESCAPED_SLASHES),
                    'provider_priority' => json_encode($this->denormalizeProviderArray($priority), JSON_UNESCAPED_SLASHES),
                ]);
        }
    }

    private function revertApplicationSettings(): void
    {
        if (! Schema::hasTable('application_settings')) {
            return;
        }

        if (Schema::hasColumn('application_settings', 'value')) {
            DB::table('application_settings')
                ->where('key', 'integrations.default_provider')
                ->where('value', self::CANONICAL_PROVIDER)
                ->update(['value' => self::LEGACY_PROVIDER]);
        }

        if (Schema::hasColumn('application_settings', 'provider')) {
            DB::table('application_settings')
                ->where('provider', self::CANONICAL_PROVIDER)
                ->update(['provider' => self::LEGACY_PROVIDER]);
        }
    }

    private function revertSettingsTable(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'key') || ! Schema::hasColumn('settings', 'value')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'integrations.default_provider')
            ->where('value', self::CANONICAL_PROVIDER)
            ->update(['value' => self::LEGACY_PROVIDER]);
    }

    /**
     * @return list<string>
     */
    private function decodeStringArray(mixed $raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $values = is_array($decoded) ? $decoded : [];
        } else {
            $values = [];
        }

        $out = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $out[] = $trimmed;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $providers
     * @return list<string>
     */
    private function normalizeProviderArray(array $providers): array
    {
        $out = [];
        foreach ($providers as $provider) {
            $normalized = strtolower(trim($provider));
            if ($normalized === self::LEGACY_PROVIDER) {
                $normalized = self::CANONICAL_PROVIDER;
            }
            if ($normalized !== '' && ! in_array($normalized, $out, true)) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $providers
     * @return list<string>
     */
    private function denormalizeProviderArray(array $providers): array
    {
        $out = [];
        foreach ($providers as $provider) {
            $normalized = strtolower(trim($provider));
            if ($normalized === self::CANONICAL_PROVIDER) {
                $normalized = self::LEGACY_PROVIDER;
            }
            if ($normalized !== '' && ! in_array($normalized, $out, true)) {
                $out[] = $normalized;
            }
        }

        return $out;
    }
};

