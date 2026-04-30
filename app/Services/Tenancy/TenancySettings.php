<?php

namespace App\Services\Tenancy;

use App\Services\System\SystemSettingsService;

/**
 * Opt-in tenant query scoping. When disabled (default), behaviour matches legacy single-tenant installs.
 * DB value overrides config when the application_settings row exists.
 */
final class TenancySettings
{
    public const KEY_TENANT_SCOPING_ENABLED = 'tenancy.tenant_scoping_enabled';

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function tenantScopingEnabled(): bool
    {
        return $this->settings->getBool(
            key: self::KEY_TENANT_SCOPING_ENABLED,
            default: false,
            context: ['scope' => 'platform', 'category' => 'tenancy'],
            configFallbackKey: 'tenancy.tenant_scoping_enabled'
        );
    }
}
