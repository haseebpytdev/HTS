<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side display conversion for search-result cards. Do not duplicate this
 * logic in client-side JavaScript; callers must keep original supplier amounts.
 *
 * Resolution: DB `exchange_rates` (admin overrides), then HTTP providers in order:
 * Frankfurter (ECB), Currency API (broad fiat matrix), exchangerate.host. USD
 * triangulation uses the same leg resolver for each hop.
 *
 * After a base rate is found, a configurable conversion-fee percent is applied to the
 * effective rate (default 2%) so displayed amounts include a small margin vs raw FX.
 */
class FareDisplayConversionService
{
    /**
     * @return array{
     *   original_currency:string,
     *   original_amount:float,
     *   original_formatted:string,
     *   display_currency:string,
     *   display_amount:float,
     *   display_formatted:string,
     *   exchange_rate:float|null,
     *   converted:bool,
     *   fallback_to_original:bool,
     *   display_amount_unavailable?:bool,
     *   base_exchange_rate?:float|null,
     *   conversion_fee_percent?:float|null
     * }
     */
    public function convert(float $amount, string $supplierCurrency, string $displayCurrency): array
    {
        $originalCurrency = strtoupper(trim($supplierCurrency));
        $targetCurrency = strtoupper(trim($displayCurrency));
        $normalizedAmount = round($amount, 2);

        if ($originalCurrency === '' || $targetCurrency === '' || $originalCurrency === $targetCurrency) {
            return $this->buildResponse(
                originalCurrency: $originalCurrency !== '' ? $originalCurrency : $targetCurrency,
                originalAmount: $normalizedAmount,
                displayCurrency: $originalCurrency !== '' ? $originalCurrency : $targetCurrency,
                displayAmount: $normalizedAmount,
                exchangeRate: 1.0,
                converted: false,
                fallbackToOriginal: false,
                baseExchangeRate: null,
                conversionFeePercent: null,
            );
        }

        $rate = $this->lookupRate($originalCurrency, $targetCurrency);

        if ($rate === null || $rate <= 0) {
            Log::notice('display_fx.unavailable_after_providers', [
                'from' => $originalCurrency,
                'to' => $targetCurrency,
            ]);

            return [
                'original_currency' => $originalCurrency,
                'original_amount' => $normalizedAmount,
                'original_formatted' => number_format($normalizedAmount, 2),
                'display_currency' => $targetCurrency,
                'display_amount' => 0.0,
                'display_formatted' => '',
                'exchange_rate' => null,
                'converted' => false,
                'fallback_to_original' => true,
                'display_amount_unavailable' => true,
                'base_exchange_rate' => null,
                'conversion_fee_percent' => null,
            ];
        }

        $feePercent = $this->conversionFeePercent();
        $effectiveRate = $rate * (1 + ($feePercent / 100.0));

        return $this->buildResponse(
            originalCurrency: $originalCurrency,
            originalAmount: $normalizedAmount,
            displayCurrency: $targetCurrency,
            displayAmount: round($normalizedAmount * $effectiveRate, 2),
            exchangeRate: $effectiveRate,
            converted: true,
            fallbackToOriginal: false,
            baseExchangeRate: $rate,
            conversionFeePercent: $feePercent,
        );
    }

    private function conversionFeePercent(): float
    {
        $p = (float) config('services.display_currency.conversion_fee_percent', 2.0);

        return max(0.0, min($p, 99.0));
    }

    private function httpFxAllowed(): bool
    {
        return (bool) config('services.display_fx.http_providers_enabled', true);
    }

    private function lookupRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $leg = $this->lookupLeg($baseCurrency, $targetCurrency);
        if ($leg !== null && $leg > 0) {
            return $leg;
        }

        if ($baseCurrency !== 'USD' && $targetCurrency !== 'USD') {
            $toUsd = $this->lookupLeg($baseCurrency, 'USD');
            if ($toUsd !== null && $toUsd > 0) {
                $usdToTarget = $this->lookupLeg('USD', $targetCurrency);
                if ($usdToTarget !== null && $usdToTarget > 0) {
                    return round($toUsd * $usdToTarget, 8);
                }
            }
        }

        return null;
    }

    /**
     * One conversion step: DB (direct or inverse), then HTTP providers (Frankfurter → Currency API → exchangerate.host).
     */
    private function lookupLeg(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $db = $this->lookupDirectOrInverseDb($from, $to);
        if ($db !== null && $db > 0) {
            return $db;
        }

        if (! $this->httpFxAllowed()) {
            return null;
        }

        if (config('services.frankfurter.display_fx_enabled', true)) {
            $rate = $this->lookupFrankfurter($from, $to);
            if ($rate !== null && $rate > 0) {
                return $rate;
            }
        }

        if (config('services.currency_api.display_fx_enabled', true)) {
            $rate = $this->lookupCurrencyApi($from, $to);
            if ($rate !== null && $rate > 0) {
                return $rate;
            }
        }

        if (config('services.exchangerate_host.display_fx_enabled', true)) {
            $rate = $this->lookupExchangeRateHost($from, $to);
            if ($rate !== null && $rate > 0) {
                return $rate;
            }
        }

        return null;
    }

    private function lookupDirectOrInverseDb(string $baseCurrency, string $targetCurrency): ?float
    {
        if (! Schema::hasTable('exchange_rates')) {
            return null;
        }

        $direct = ExchangeRate::query()
            ->whereRaw('UPPER(base_currency_code) = ?', [$baseCurrency])
            ->whereRaw('UPPER(target_currency_code) = ?', [$targetCurrency])
            ->where(function ($query): void {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($direct !== null && is_numeric($direct->rate) && (float) $direct->rate > 0) {
            return (float) $direct->rate;
        }

        $inverse = ExchangeRate::query()
            ->whereRaw('UPPER(base_currency_code) = ?', [$targetCurrency])
            ->whereRaw('UPPER(target_currency_code) = ?', [$baseCurrency])
            ->where(function ($query): void {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($inverse === null || ! is_numeric($inverse->rate) || (float) $inverse->rate <= 0) {
            return null;
        }

        return round(1 / (float) $inverse->rate, 8);
    }

    private function lookupFrankfurter(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $cacheKey = 'fx.frankfurter.'.$from.'.'.$to;
        $ttl = (int) config('services.frankfurter.cache_seconds', 3600);

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return is_numeric($cached) && (float) $cached > 0 ? (float) $cached : null;
        }

        $baseUrl = rtrim((string) config('services.frankfurter.base_url', 'https://api.frankfurter.app'), '/');
        $timeout = (int) config('services.frankfurter.timeout_seconds', 5);
        $url = $baseUrl.'/v1/latest';

        try {
            $response = Http::timeout($timeout)->get($url, [
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Frankfurter FX request failed', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $rates = $response->json('rates');
        if (! is_array($rates) || ! isset($rates[$to])) {
            return null;
        }

        $rate = (float) $rates[$to];
        if ($rate <= 0) {
            return null;
        }

        Cache::put($cacheKey, $rate, $ttl);

        return $rate;
    }

    /**
     * Broad fiat matrix (one JSON per base currency); cached per base.
     */
    private function lookupCurrencyApi(string $from, string $to): ?float
    {
        $fromL = strtolower($from);
        $toL = strtolower($to);
        $cacheKey = 'fx.currency-api.bucket.'.$fromL;
        $ttl = (int) config('services.currency_api.cache_seconds', 3600);
        $baseUrl = rtrim((string) config('services.currency_api.base_url', 'https://latest.currency-api.pages.dev/v1/currencies'), '/');

        $bucket = Cache::get($cacheKey);
        if (! is_array($bucket)) {
            $bucket = $this->fetchCurrencyApiBucket($baseUrl, $fromL);
            if (is_array($bucket)) {
                Cache::put($cacheKey, $bucket, $ttl);
            }
        }

        if (! is_array($bucket) || ! isset($bucket[$toL])) {
            return null;
        }

        $rate = (float) $bucket[$toL];

        return $rate > 0 ? $rate : null;
    }

    /**
     * @return array<string, float>|null
     */
    private function fetchCurrencyApiBucket(string $baseUrl, string $fromLower): ?array
    {
        $url = $baseUrl.'/'.$fromLower.'.json';
        $timeout = (int) config('services.currency_api.timeout_seconds', 8);

        try {
            $response = Http::timeout($timeout)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            Log::notice('Currency-API FX request failed', [
                'from' => $fromLower,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::notice('Currency-API FX bad status', ['from' => $fromLower, 'status' => $response->status()]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $inner = $data[$fromLower] ?? null;
        if (! is_array($inner)) {
            return null;
        }

        $normalized = [];
        foreach ($inner as $code => $value) {
            if (is_string($code) && is_numeric($value)) {
                $normalized[strtolower($code)] = (float) $value;
            }
        }

        return $normalized !== [] ? $normalized : null;
    }

    private function lookupExchangeRateHost(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $cacheKey = 'fx.exchangerate.host.'.$from.'.'.$to;
        $ttl = (int) config('services.exchangerate_host.cache_seconds', 3600);

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return is_numeric($cached) && (float) $cached > 0 ? (float) $cached : null;
        }

        $baseUrl = rtrim((string) config('services.exchangerate_host.base_url', 'https://api.exchangerate.host'), '/');
        $timeout = (int) config('services.exchangerate_host.timeout_seconds', 8);
        $url = $baseUrl.'/latest';

        try {
            $response = Http::timeout($timeout)->get($url, [
                'base' => $from,
                'symbols' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::notice('ExchangeRate.host FX request failed', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $rates = $response->json('rates');
        if (! is_array($rates)) {
            return null;
        }

        $candidates = [$to, strtolower($to), strtoupper($to)];
        $rate = null;
        foreach ($candidates as $key) {
            if (isset($rates[$key]) && is_numeric($rates[$key])) {
                $rate = (float) $rates[$key];
                break;
            }
        }
        if ($rate === null) {
            return null;
        }
        if ($rate <= 0) {
            return null;
        }

        Cache::put($cacheKey, $rate, $ttl);

        return $rate;
    }

    /**
     * @return array{
     *   original_currency:string,
     *   original_amount:float,
     *   original_formatted:string,
     *   display_currency:string,
     *   display_amount:float,
     *   display_formatted:string,
     *   exchange_rate:float|null,
     *   converted:bool,
     *   fallback_to_original:bool,
     *   display_amount_unavailable?:bool,
     *   base_exchange_rate?:float|null,
     *   conversion_fee_percent?:float|null
     * }
     */
    private function buildResponse(
        string $originalCurrency,
        float $originalAmount,
        string $displayCurrency,
        float $displayAmount,
        ?float $exchangeRate,
        bool $converted,
        bool $fallbackToOriginal,
        ?float $baseExchangeRate = null,
        ?float $conversionFeePercent = null,
    ): array {
        return [
            'original_currency' => $originalCurrency,
            'original_amount' => $originalAmount,
            'original_formatted' => number_format($originalAmount, 2),
            'display_currency' => $displayCurrency,
            'display_amount' => $displayAmount,
            'display_formatted' => number_format($displayAmount, 2),
            'exchange_rate' => $exchangeRate,
            'converted' => $converted,
            'fallback_to_original' => $fallbackToOriginal,
            'display_amount_unavailable' => false,
            'base_exchange_rate' => $baseExchangeRate,
            'conversion_fee_percent' => $conversionFeePercent,
        ];
    }
}
