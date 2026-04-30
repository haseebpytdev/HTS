<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Services\System\SystemSettingsService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DisplayCurrencyResolver
{
    /**
     * Always allowed for “Show prices in” even if not seeded in `currencies`.
     *
     * @var list<string>
     */
    private const COMMON_DISPLAY_CODES = [
        'AED', 'AUD', 'EUR', 'GBP', 'PKR', 'SAR', 'USD',
    ];

    /**
     * @var array<string, string>
     */
    private const COUNTRY_CURRENCY_MAP = [
        'AE' => 'AED',
        'GB' => 'GBP',
        'PK' => 'PKR',
        'SA' => 'SAR',
        'US' => 'USD',
    ];

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array{currency:string,source:string,country:?string}
     */
    public function resolve(Request $request): array
    {
        $headerCurrency = $this->normalizeCurrencyCode(
            $request->headers->get('X-Display-Currency')
                ?? $request->headers->get('X-Currency')
        );

        if ($headerCurrency !== null && $this->isSupportedCurrency($headerCurrency)) {
            $request->session()->put('display_currency', $headerCurrency);

            return [
                'currency' => $headerCurrency,
                'source' => 'session_selection',
                'country' => $this->extractCountryCode($request),
            ];
        }

        $queryCurrency = $this->normalizeCurrencyCode((string) $request->query('display_currency', ''));
        if ($queryCurrency !== null && $this->isSupportedCurrency($queryCurrency)) {
            $request->session()->put('display_currency', $queryCurrency);

            return [
                'currency' => $queryCurrency,
                'source' => 'manual_query',
                'country' => $this->extractCountryCode($request),
            ];
        }

        $sessionCurrency = $this->normalizeCurrencyCode((string) $request->session()->get('display_currency', ''));
        if ($sessionCurrency !== null && $this->isSupportedCurrency($sessionCurrency)) {
            return [
                'currency' => $sessionCurrency,
                'source' => 'session_selection',
                'country' => $this->extractCountryCode($request),
            ];
        }

        $userCurrency = $this->resolveAuthenticatedUserCurrency($request);
        if ($userCurrency !== null && $this->isSupportedCurrency($userCurrency)) {
            return [
                'currency' => $userCurrency,
                'source' => 'user_preference',
                'country' => $this->extractCountryCode($request),
            ];
        }

        $country = $this->extractCountryCode($request);
        $countryCurrency = $country !== null ? (self::COUNTRY_CURRENCY_MAP[$country] ?? null) : null;
        if ($countryCurrency !== null && $this->isSupportedCurrency($countryCurrency)) {
            return [
                'currency' => $countryCurrency,
                'source' => 'ip_country',
                'country' => $country,
            ];
        }

        $defaultCurrency = $this->normalizeCurrencyCode(
            $this->settings->getString(
                'app.localization.default_currency',
                'PKR',
                ['scope' => 'platform', 'category' => 'app'],
            ) ?? 'PKR'
        ) ?? 'PKR';

        return [
            'currency' => $defaultCurrency,
            'source' => 'platform_default',
            'country' => $country,
        ];
    }

    /**
     * Active ISO codes for UI pickers (manual display-currency override).
     *
     * @return list<string>
     */
    public function listSelectableCurrencies(): array
    {
        if (! Schema::hasTable('currencies')) {
            return array_values(array_unique(array_merge(
                self::COMMON_DISPLAY_CODES,
                array_values(self::COUNTRY_CURRENCY_MAP),
            )));
        }

        $codes = Currency::query()
            ->where(function ($query): void {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->orderBy('code')
            ->pluck('code')
            ->map(static fn ($code): string => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();

        $merged = array_values(array_unique(array_merge(self::COMMON_DISPLAY_CODES, $codes)));
        sort($merged);

        return $merged !== [] ? $merged : ['PKR', 'USD'];
    }

    private function resolveAuthenticatedUserCurrency(Request $request): ?string
    {
        /** @var Authenticatable|null $user */
        $user = $request->user();
        if ($user !== null) {
            $currency = $this->extractCurrencyFromPrincipal($user);
            if ($currency !== null) {
                return $currency;
            }
        }

        foreach (['web', 'customer'] as $guard) {
            try {
                /** @var Authenticatable|null $guardUser */
                $guardUser = Auth::guard($guard)->user();
            } catch (\Throwable) {
                $guardUser = null;
            }

            if ($guardUser === null) {
                continue;
            }

            $currency = $this->extractCurrencyFromPrincipal($guardUser);
            if ($currency !== null) {
                return $currency;
            }
        }

        return null;
    }

    private function extractCurrencyFromPrincipal(Authenticatable $user): ?string
    {
        foreach (['preferred_currency', 'currency_code', 'display_currency'] as $field) {
            $value = $this->normalizeCurrencyCode(data_get($user, $field));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractCountryCode(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code'] as $header) {
            $value = strtoupper(trim((string) $request->headers->get($header, '')));
            if ($value !== '' && $value !== 'XX') {
                return $value;
            }
        }

        return null;
    }

    private function isSupportedCurrency(string $code): bool
    {
        if (in_array($code, self::COMMON_DISPLAY_CODES, true)) {
            return true;
        }

        if (! Schema::hasTable('currencies')) {
            return true;
        }

        return Currency::query()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where(function ($query): void {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->exists();
    }

    private function normalizeCurrencyCode(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $code = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }
}
