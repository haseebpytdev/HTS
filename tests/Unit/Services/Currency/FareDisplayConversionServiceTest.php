<?php

namespace Tests\Unit\Services\Currency;

use App\Models\ExchangeRate;
use App\Services\Currency\FareDisplayConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FareDisplayConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_via_usd_triangulation_when_direct_pair_missing(): void
    {
        config(['services.display_fx.http_providers_enabled' => false]);

        ExchangeRate::query()->create([
            'base_currency_code' => 'USD',
            'target_currency_code' => 'PKR',
            'rate' => 278.5,
            'effective_at' => now(),
            'is_active' => true,
        ]);

        ExchangeRate::query()->create([
            'base_currency_code' => 'USD',
            'target_currency_code' => 'GBP',
            'rate' => 0.79,
            'effective_at' => now(),
            'is_active' => true,
        ]);

        $service = app(FareDisplayConversionService::class);
        $result = $service->convert(100.0, 'GBP', 'PKR');

        $this->assertTrue($result['converted']);
        $this->assertFalse($result['fallback_to_original']);
        $this->assertSame('PKR', $result['display_currency']);

        $baseRate = (1 / 0.79) * 278.5;
        $expected = round(100.0 * $baseRate * 1.02, 2);
        $this->assertEqualsWithDelta($expected, $result['display_amount'], 0.02);
        $this->assertSame(2.0, $result['conversion_fee_percent']);
        $this->assertNotNull($result['base_exchange_rate']);
        $this->assertEqualsWithDelta($baseRate * 1.02, (float) $result['exchange_rate'], 0.0001);
    }

    public function test_uses_frankfurter_when_db_missing_and_enabled(): void
    {
        ExchangeRate::query()->delete();

        config([
            'services.display_fx.http_providers_enabled' => true,
            'services.frankfurter.display_fx_enabled' => true,
            'services.currency_api.display_fx_enabled' => false,
            'services.exchangerate_host.display_fx_enabled' => false,
            'services.frankfurter.base_url' => 'https://api.frankfurter.app',
        ]);

        Http::fake([
            'https://api.frankfurter.app/v1/latest*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'rates' => ['PKR' => 278.5],
            ], 200),
        ]);

        $service = app(FareDisplayConversionService::class);
        $result = $service->convert(100.0, 'USD', 'PKR');

        $this->assertTrue($result['converted']);
        $this->assertSame('PKR', $result['display_currency']);
        $this->assertEqualsWithDelta(100.0 * 278.5 * 1.02, $result['display_amount'], 0.01);
        $this->assertSame(278.5, $result['base_exchange_rate']);
        $this->assertEqualsWithDelta(278.5 * 1.02, (float) $result['exchange_rate'], 0.0001);

        Http::assertSent(function ($request): bool {
            $url = $request->url();

            return str_contains($url, 'api.frankfurter.app')
                && str_contains($url, 'from=USD')
                && str_contains($url, 'to=PKR');
        });
    }

    public function test_uses_currency_api_matrix_when_frankfurter_disabled(): void
    {
        ExchangeRate::query()->delete();

        config([
            'services.display_fx.http_providers_enabled' => true,
            'services.frankfurter.display_fx_enabled' => false,
            'services.currency_api.display_fx_enabled' => true,
            'services.exchangerate_host.display_fx_enabled' => false,
            'services.currency_api.base_url' => 'https://latest.currency-api.pages.dev/v1/currencies',
        ]);

        Http::fake([
            'https://latest.currency-api.pages.dev/v1/currencies/usd.json' => Http::response([
                'date' => '2026-01-01',
                'usd' => [
                    'pkr' => 278.5,
                ],
            ], 200),
        ]);

        $service = app(FareDisplayConversionService::class);
        $result = $service->convert(100.0, 'USD', 'PKR');

        $this->assertTrue($result['converted']);
        $this->assertSame('PKR', $result['display_currency']);
        $this->assertEqualsWithDelta(100.0 * 278.5 * 1.02, $result['display_amount'], 0.02);
    }
}
