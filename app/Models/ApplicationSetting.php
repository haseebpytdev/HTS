<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ApplicationSetting extends Model
{
    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_MODULE_PROVIDER = 'module_provider';
    public const SCOPE_USER = 'user';

    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';
    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_JSON = 'json';

    public const CATEGORY_BRANDING = 'branding';
    public const CATEGORY_LOCALIZATION = 'localization';
    public const CATEGORY_BOOKING = 'booking';
    public const CATEGORY_PRICING = 'pricing';
    public const CATEGORY_TAX = 'tax';
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_INTEGRATIONS = 'integrations';
    public const CATEGORY_NOTIFICATIONS = 'notifications';
    public const CATEGORY_APPROVALS = 'approvals';
    public const CATEGORY_DOCUMENTS_SECURITY = 'documents_security';
    public const CATEGORY_SEO_CMS = 'seo_cms';
    public const CATEGORY_TENANCY = 'tenancy';
    public const CATEGORY_ANALYTICS_REPORTING = 'analytics_reporting';

    protected $fillable = [
        'scope',
        'scope_id',
        'provider',
        'module',
        'category',
        'key',
        'value',
        'value_type',
    ];

    protected $casts = [
        'scope_id' => 'integer',
    ];

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null
     * }  $filters
     */
    public static function getValue(string $key, ?string $default = null, array $filters = []): ?string
    {
        return static::query()
            ->forScope(
                (string) ($filters['scope'] ?? self::SCOPE_PLATFORM),
                $filters['scope_id'] ?? null
            )
            ->forProvider($filters['provider'] ?? null)
            ->forModule($filters['module'] ?? null)
            ->forCategory($filters['category'] ?? null)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    /**
     * @param  array{
     *   scope?: string,
     *   scope_id?: int|null,
     *   provider?: string|null,
     *   module?: string|null,
     *   category?: string|null,
     *   value_type?: string|null
     * }  $filters
     */
    public static function setValue(string $key, ?string $value, array $filters = []): void
    {
        $scope = self::canonicalScope($filters['scope'] ?? null);
        $scopeId = $filters['scope_id'] ?? null;
        $provider = $filters['provider'] ?? null;
        $module = $filters['module'] ?? null;
        $category = static::canonicalCategory($filters['category'] ?? static::categoryFromKey($key));
        $valueType = static::canonicalValueType($filters['value_type'] ?? null);

        static::query()->updateOrCreate(
            [
                'scope' => $scope,
                'scope_id' => $scopeId,
                'provider' => static::normalizeNullableString($provider),
                'module' => static::normalizeNullableString($module),
                'category' => static::normalizeNullableString($category),
                'key' => $key,
            ],
            [
                'value' => $value,
                'value_type' => (string) $valueType,
            ]
        );
    }

    /**
     * @return array<string, string|null>
     */
    public static function getCategory(string $category): array
    {
        $category = static::canonicalCategory($category) ?? trim($category);
        $prefix = $category.'.';

        return static::query()
            ->forScope(self::SCOPE_PLATFORM)
            ->where('category', $category)
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key): array => [str_replace($prefix, '', (string) $key) => $value])
            ->all();
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function setCategory(string $category, array $values): void
    {
        $category = static::canonicalCategory($category) ?? trim($category);
        $prefix = $category.'.';
        foreach ($values as $key => $value) {
            static::setValue($prefix.$key, $value, [
                'scope' => self::SCOPE_PLATFORM,
                'category' => $category,
            ]);
        }
    }

    public function scopeForScope(Builder $query, string $scope, ?int $scopeId = null): Builder
    {
        $scope = static::canonicalScope($scope);

        return $query
            ->where('scope', $scope)
            ->where('scope_id', $scopeId);
    }

    public function scopeForProvider(Builder $query, ?string $provider): Builder
    {
        $provider = static::normalizeNullableString($provider);
        if ($provider === null) {
            return $query->whereNull('provider');
        }

        return $query->where('provider', $provider);
    }

    public function scopeForModule(Builder $query, ?string $module): Builder
    {
        $module = static::normalizeNullableString($module);
        if ($module === null) {
            return $query->whereNull('module');
        }

        return $query->where('module', $module);
    }

    public function scopeForCategory(Builder $query, ?string $category): Builder
    {
        $category = static::normalizeNullableString($category);
        if ($category === null) {
            return $query;
        }

        return $query->where('category', $category);
    }

    private static function categoryFromKey(string $key): ?string
    {
        $parts = explode('.', trim($key), 2);
        $first = trim((string) ($parts[0] ?? ''));

        return $first !== '' ? static::canonicalCategory($first) : null;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        $normalized = is_string($value) ? trim($value) : '';

        return $normalized === '' ? null : $normalized;
    }

    public static function canonicalScope(mixed $scope): string
    {
        $raw = is_string($scope) ? strtolower(trim($scope)) : '';

        return match ($raw) {
            self::SCOPE_PLATFORM,
            self::SCOPE_TENANT,
            self::SCOPE_MODULE_PROVIDER,
            self::SCOPE_USER => $raw,
            default => self::SCOPE_PLATFORM,
        };
    }

    public static function canonicalCategory(mixed $category): ?string
    {
        if (! is_string($category)) {
            return null;
        }

        $raw = strtolower(trim($category));
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(['/', '-', ' '], '_', $raw);

        return match ($normalized) {
            'payments' => self::CATEGORY_PAYMENT,
            'documents', 'security', 'documents_security' => self::CATEGORY_DOCUMENTS_SECURITY,
            'seo', 'cms', 'seo_cms' => self::CATEGORY_SEO_CMS,
            'analytics', 'reporting', 'analytics_reporting' => self::CATEGORY_ANALYTICS_REPORTING,
            default => $normalized,
        };
    }

    public static function canonicalValueType(mixed $valueType): string
    {
        $raw = is_string($valueType) ? strtolower(trim($valueType)) : '';

        return match ($raw) {
            self::TYPE_BOOL,
            self::TYPE_INT,
            self::TYPE_FLOAT,
            self::TYPE_JSON => $raw,
            default => self::TYPE_STRING,
        };
    }
}
