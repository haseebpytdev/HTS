<?php

namespace App\Services\System;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Schema;

class SystemSettingsService
{
    /** @var bool|null Null = not yet resolved; avoids repeated Schema::hasTable/hasColumns on every read. */
    private static ?bool $settingsTableReady = null;

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     */
    public function getString(
        string $key,
        ?string $default = null,
        array $context = [],
        ?string $configFallbackKey = null
    ): ?string {
        $fallback = $default;
        if ($configFallbackKey !== null && $configFallbackKey !== '') {
            /** @var mixed $configValue */
            $configValue = config($configFallbackKey, $default);
            $fallback = $this->normalizeToString($configValue, $default);
        }

        if (! $this->hasSettingsTable()) {
            return $fallback;
        }

        return ApplicationSetting::getValue($key, $fallback, $this->normalizeContext($context));
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     */
    public function getBool(
        string $key,
        bool $default = false,
        array $context = [],
        ?string $configFallbackKey = null
    ): bool {
        $stringDefault = $default ? '1' : '0';
        $stored = $this->getString($key, $stringDefault, $context, $configFallbackKey);

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     */
    public function getInt(
        string $key,
        int $default = 0,
        array $context = [],
        ?string $configFallbackKey = null
    ): int {
        $stored = $this->getString($key, (string) $default, $context, $configFallbackKey);
        if ($stored === null || ! is_numeric($stored)) {
            return $default;
        }

        return (int) $stored;
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     */
    public function getFloat(
        string $key,
        float $default = 0.0,
        array $context = [],
        ?string $configFallbackKey = null
    ): float {
        $stored = $this->getString($key, (string) $default, $context, $configFallbackKey);
        if ($stored === null || ! is_numeric($stored)) {
            return $default;
        }

        return (float) $stored;
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public function getArray(
        string $key,
        array $default = [],
        array $context = [],
        ?string $configFallbackKey = null
    ): array {
        $fallback = $default;
        if ($configFallbackKey !== null && $configFallbackKey !== '') {
            /** @var mixed $configValue */
            $configValue = config($configFallbackKey, $default);
            $fallback = is_array($configValue) ? $configValue : $default;
        }

        $stored = $this->getString($key, null, $context, null);
        if ($stored === null || $stored === '') {
            return $fallback;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($stored, true);

        return is_array($decoded) ? $decoded : $fallback;
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null,
     *   value_type?: string|null
     * }  $context
     */
    public function set(string $key, mixed $value, array $context = []): void
    {
        if (! $this->hasSettingsTable()) {
            return;
        }

        $normalizedContext = $this->normalizeContext($context);
        $normalizedContext['value_type'] = $this->resolveValueType($value);

        ApplicationSetting::setValue(
            $key,
            $this->serializeValue($value),
            $normalizedContext
        );
    }

    /**
     * @param  array<string, scalar|null>  $settings
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     */
    public function setMany(array $settings, array $context = []): void
    {
        foreach ($settings as $key => $value) {
            $this->set((string) $key, $value, $context);
        }
    }

    private function hasSettingsTable(): bool
    {
        if (self::$settingsTableReady !== null) {
            return self::$settingsTableReady;
        }

        if (! Schema::hasTable('application_settings')) {
            return self::$settingsTableReady = false;
        }

        // Enterprise settings reads require scoped columns from EA-2 migration.
        return self::$settingsTableReady = Schema::hasColumns('application_settings', [
            'scope',
            'scope_id',
            'provider',
            'module',
            'category',
            'value_type',
        ]);
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $context
     * @return array{
     *   scope: string,
     *   scope_id: int|null,
     *   provider: string|null,
     *   module: string|null,
     *   category: string|null
     * }
     */
    private function normalizeContext(array $context): array
    {
        return [
            'scope' => ApplicationSetting::canonicalScope($context['scope'] ?? null),
            'scope_id' => $context['scope_id'] ?? null,
            'provider' => $this->nullableString($context['provider'] ?? null),
            'module' => $this->nullableString($context['module'] ?? null),
            'category' => ApplicationSetting::canonicalCategory($context['category'] ?? null),
        ];
    }

    private function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);

            return is_string($json) ? $json : null;
        }

        return null;
    }

    private function resolveValueType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => ApplicationSetting::TYPE_BOOL,
            is_int($value) => ApplicationSetting::TYPE_INT,
            is_float($value) => ApplicationSetting::TYPE_FLOAT,
            is_array($value) => ApplicationSetting::TYPE_JSON,
            default => ApplicationSetting::TYPE_STRING,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeToString(mixed $value, ?string $default): ?string
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }
}
