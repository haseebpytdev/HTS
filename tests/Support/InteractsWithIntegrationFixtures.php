<?php

namespace Tests\Support;

use App\Integrations\Amadeus\AmadeusBookingAdapter;
use App\Integrations\Amadeus\AmadeusFlightPriceAdapter;
use App\Integrations\Amadeus\AmadeusFlightSearchAdapter;
use App\Integrations\Sabre\SabreBookingAdapter;
use App\Integrations\Sabre\SabreFlightPriceAdapter;
use App\Integrations\Sabre\SabreFlightSearchAdapter;
use App\Integrations\Stub\StubBookingAdapter;
use App\Integrations\Stub\StubFlightPriceAdapter;
use App\Integrations\Stub\StubFlightSearchAdapter;
use App\Integrations\Travelport\TravelportBookingAdapter;
use App\Integrations\Travelport\TravelportFlightPriceAdapter;
use App\Integrations\Travelport\TravelportFlightSearchAdapter;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Support\Facades\Cache;

trait InteractsWithIntegrationFixtures
{
    protected function resetIntegrationFixtureConfig(array $providers = ['travelport', 'sabre', 'amadeus']): void
    {
        Cache::flush();
        config(['integrations.stub_provider_scenarios' => []]);
        $this->seedAuthorizedIntegrationProviders($providers);
    }

    protected function bindProviderSimulators(): void
    {
        $this->app->bind(TravelportFlightSearchAdapter::class, fn () => new StubFlightSearchAdapter('travelport'));
        $this->app->bind(SabreFlightSearchAdapter::class, fn () => new StubFlightSearchAdapter('sabre'));
        $this->app->bind(AmadeusFlightSearchAdapter::class, fn () => new StubFlightSearchAdapter('amadeus'));

        $this->app->bind(TravelportFlightPriceAdapter::class, fn () => new StubFlightPriceAdapter('travelport'));
        $this->app->bind(SabreFlightPriceAdapter::class, fn () => new StubFlightPriceAdapter('sabre'));
        $this->app->bind(AmadeusFlightPriceAdapter::class, fn () => new StubFlightPriceAdapter('amadeus'));

        $this->app->bind(TravelportBookingAdapter::class, fn () => new StubBookingAdapter('travelport'));
        $this->app->bind(SabreBookingAdapter::class, fn () => new StubBookingAdapter('sabre'));
        $this->app->bind(AmadeusBookingAdapter::class, fn () => new StubBookingAdapter('amadeus'));
    }

    /**
     * @param  list<string>  $providers
     */
    protected function seedAuthorizedIntegrationProviders(array $providers): void
    {
        $defaultSlug = (string) config('tenancy.default_slug', 'default');
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => $defaultSlug],
            ['name' => 'Test Tenant', 'plan_tier' => 'enterprise']
        );

        foreach (array_values($providers) as $index => $provider) {
            TenantProviderAccess::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'provider' => $provider],
                [
                    'is_enabled' => true,
                    'can_search' => true,
                    'can_price' => true,
                    'can_book' => true,
                    'allow_multi_provider' => true,
                    'allow_fallback' => true,
                    'priority_order' => ($index + 1) * 10,
                ]
            );

            IntegrationConnection::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'provider' => $provider,
                    'environment' => 'production',
                ],
                [
                    'name' => strtoupper($provider).' Test Connection',
                    'account_name' => strtoupper($provider).' Account',
                    'is_active' => true,
                    'status' => 'healthy',
                ]
            );
        }
    }
}
