<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Contracts\Documents\DocumentScannerInterface;
use App\Contracts\Communication\EmailChannelInterface;
use App\Contracts\Communication\InAppNotificationChannelInterface;
use App\Contracts\Communication\SmsChannelInterface;
use App\Contracts\Communication\WhatsappChannelInterface;
use App\Communication\Channels\DatabaseInAppNotificationChannel;
use App\Communication\Channels\LaravelMailEmailChannel;
use App\Communication\Channels\OpenSourceWebhookSmsChannel;
use App\Communication\Channels\OpenSourceWebhookWhatsappChannel;
use App\Contracts\Automation\AutomationProviderInterface;
use App\Automation\Events\BookingConfirmed;
use App\Automation\Events\InquiryFollowUpScheduled;
use App\Automation\Listeners\TriggerBookingConfirmedAutomation;
use App\Automation\Listeners\TriggerInquiryFollowUpScheduledAutomation;
use App\Automation\Providers\OpenSourceWebhookAutomationProvider;
use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Enums\UserRole;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\AmadeusAuthService;
use App\Integrations\Amadeus\AmadeusBookingAdapter;
use App\Integrations\Amadeus\AmadeusClient;
use App\Integrations\Amadeus\AmadeusFlightPriceAdapter;
use App\Integrations\Amadeus\AmadeusFlightSearchAdapter;
use App\Integrations\Duffel\DuffelAuthService;
use App\Integrations\Duffel\DuffelBookingAdapter;
use App\Integrations\Duffel\DuffelClient;
use App\Integrations\Duffel\DuffelFlightPriceAdapter;
use App\Integrations\Duffel\DuffelFlightSearchAdapter;
use App\Integrations\Iati\IatiAuthService;
use App\Integrations\Iati\IatiBookingAdapter;
use App\Integrations\Iati\IatiClient;
use App\Integrations\Iati\IatiFlightPriceAdapter;
use App\Integrations\Iati\IatiFlightSearchAdapter;
use App\Integrations\Sabre\SabreAuthService;
use App\Integrations\Sabre\SabreBookingAdapter;
use App\Integrations\Sabre\SabreClient;
use App\Integrations\Sabre\SabreFlightPriceAdapter;
use App\Integrations\Sabre\SabreFlightSearchAdapter;
use App\Integrations\Sabre\SabreSoapClient;
use App\Integrations\Shared\SupplierJsonHttpClient;
use App\Integrations\Shared\SupplierTokenCache;
use App\Services\Integrations\IntegrationFlowRecorder;
use App\Services\Integrations\IntegrationOrchestrationService;
use App\Services\Integrations\IntegrationProviderRegistry;
use App\Services\Integrations\ProviderCredentialResolver;
use App\Services\Finance\FinanceSettingsService;
use App\Services\System\SystemSettingsService;
use App\Services\Payment\Gateways\ManualPaymentGateway;
use App\Services\Payment\Gateways\StripePaymentGateway;
use App\Services\Payment\LedgerService;
use App\Services\Payment\PaymentService;
use App\Services\Documents\DocumentScanService;
use App\Integrations\Stub\StubAuthTokenAdapter;
use App\Integrations\Stub\StubBookingAdapter;
use App\Integrations\Stub\StubFlightPriceAdapter;
use App\Integrations\Stub\StubFlightSearchAdapter;
use App\Integrations\Travelport\TravelportAuthService;
use App\Integrations\Travelport\TravelportBookingAdapter;
use App\Integrations\Travelport\TravelportClient;
use App\Integrations\Travelport\TravelportFlightPriceAdapter;
use App\Integrations\Travelport\TravelportFlightSearchAdapter;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\User;
use App\Policies\AgencyPolicy;
use App\Policies\BookingPolicy;
use App\Policies\InquiryPolicy;
use App\Policies\QuotationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupplierTokenCache::class);

        $this->app->singleton(IntegrationProviderRegistry::class);
        $this->app->singleton(IntegrationOrchestrationService::class);
        $this->app->singleton(IntegrationFlowRecorder::class);

        $this->app->singleton(LedgerService::class);
        $this->app->singleton(EmailChannelInterface::class, function ($app): EmailChannelInterface {
            return match (config('communication.channels.email', 'laravel_mail')) {
                default => $app->make(LaravelMailEmailChannel::class),
            };
        });
        $this->app->singleton(WhatsappChannelInterface::class, function ($app): WhatsappChannelInterface {
            return match (config('communication.channels.whatsapp', 'open_source_webhook')) {
                default => $app->make(OpenSourceWebhookWhatsappChannel::class),
            };
        });
        $this->app->singleton(SmsChannelInterface::class, function ($app): SmsChannelInterface {
            return match (config('communication.channels.sms', 'open_source_webhook')) {
                default => $app->make(OpenSourceWebhookSmsChannel::class),
            };
        });
        $this->app->singleton(InAppNotificationChannelInterface::class, function ($app): InAppNotificationChannelInterface {
            return match (config('communication.channels.notification', 'database')) {
                default => $app->make(DatabaseInAppNotificationChannel::class),
            };
        });
        $this->app->singleton(AutomationProviderInterface::class, function ($app): AutomationProviderInterface {
            return match (config('automation.provider', 'open_source_webhook')) {
                default => $app->make(OpenSourceWebhookAutomationProvider::class),
            };
        });

        $this->app->singleton(PaymentGatewayInterface::class, function ($app): PaymentGatewayInterface {
            $driver = $app->make(FinanceSettingsService::class)->all()['payment_gateway_default'] ?? config('payments.driver', 'manual');

            return match ($driver) {
                'stripe' => $app->make(StripePaymentGateway::class),
                default => $app->make(ManualPaymentGateway::class),
            };
        });

        $this->app->singleton(PaymentService::class);
        $this->app->singleton(DocumentScannerInterface::class, fn () => DocumentScanService::buildConfiguredScanner());
        $this->app->singleton(DocumentScanService::class);

        $this->registerSupplierSingletons();

        $this->app->singleton(FlightSearchProviderInterface::class, function ($app): FlightSearchProviderInterface {
            return match (config('integrations.driver', config('supplier_integration.driver', 'stub'))) {
                'travelport' => $app->make(TravelportFlightSearchAdapter::class),
                'sabre' => $app->make(SabreFlightSearchAdapter::class),
                AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE => $app->make(AmadeusFlightSearchAdapter::class),
                'iati' => $app->make(IatiFlightSearchAdapter::class),
                'duffel' => $app->make(DuffelFlightSearchAdapter::class),
                default => $app->make(StubFlightSearchAdapter::class),
            };
        });

        $this->app->singleton(FlightPricingProviderInterface::class, function ($app): FlightPricingProviderInterface {
            return match (config('integrations.driver', config('supplier_integration.driver', 'stub'))) {
                'travelport' => $app->make(TravelportFlightPriceAdapter::class),
                'sabre' => $app->make(SabreFlightPriceAdapter::class),
                AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE => $app->make(AmadeusFlightPriceAdapter::class),
                'iati' => $app->make(IatiFlightPriceAdapter::class),
                'duffel' => $app->make(DuffelFlightPriceAdapter::class),
                default => $app->make(StubFlightPriceAdapter::class),
            };
        });

        $this->app->singleton(BookingProviderInterface::class, function ($app): BookingProviderInterface {
            return match (config('integrations.driver', config('supplier_integration.driver', 'stub'))) {
                'travelport' => $app->make(TravelportBookingAdapter::class),
                'sabre' => $app->make(SabreBookingAdapter::class),
                AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE => $app->make(AmadeusBookingAdapter::class),
                'iati' => $app->make(IatiBookingAdapter::class),
                'duffel' => $app->make(DuffelBookingAdapter::class),
                default => $app->make(StubBookingAdapter::class),
            };
        });

        $this->app->singleton(AuthTokenProviderInterface::class, function ($app): AuthTokenProviderInterface {
            return match (config('integrations.driver', config('supplier_integration.driver', 'stub'))) {
                'travelport' => $app->make(TravelportAuthService::class),
                'sabre' => $app->make(SabreAuthService::class),
                AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE => $app->make(AmadeusAuthService::class),
                'iati' => $app->make(IatiAuthService::class),
                'duffel' => $app->make(DuffelAuthService::class),
                default => $app->make(StubAuthTokenAdapter::class),
            };
        });
    }

    /**
     * One client + auth service per vendor so tokens and HTTP config stay consistent across adapters.
     */
    private function registerSupplierSingletons(): void
    {
        $this->app->singleton(TravelportAuthService::class);
        $this->app->singleton(TravelportClient::class, function ($app): TravelportClient {
            $auth = $app->make(TravelportAuthService::class);
            $resolved = $app->make(ProviderCredentialResolver::class)->forProvider('travelport');
            $configBase = trim((string) config('travelport.base_url', ''));
            $baseUrl = $resolved->baseUrl ?? ($configBase !== '' ? $configBase : null);
            $http = new SupplierJsonHttpClient($auth, 'travelport', $baseUrl);

            return new TravelportClient($auth, $http);
        });

        $this->app->singleton(SabreAuthService::class);
        $this->app->singleton(SabreClient::class, function ($app): SabreClient {
            $auth = $app->make(SabreAuthService::class);
            $resolved = $app->make(ProviderCredentialResolver::class)->forProvider('sabre');
            $configBase = trim((string) config('sabre.base_url', ''));
            $baseUrl = $resolved->baseUrl ?? ($configBase !== '' ? $configBase : null);
            $http = new SupplierJsonHttpClient($auth, 'sabre', $baseUrl);
            $soapBase = trim((string) config('sabre.soap_base_url', ''));
            $soap = new SabreSoapClient($auth, $soapBase !== '' ? $soapBase : $baseUrl);

            return new SabreClient($auth, $http, $soap);
        });

        $this->app->singleton(AmadeusAuthService::class);
        $this->app->singleton(AmadeusClient::class, function ($app): AmadeusClient {
            $auth = $app->make(AmadeusAuthService::class);
            $resolved = $app->make(ProviderCredentialResolver::class)->forProvider(AmadeusSelfServiceProvider::CODE);
            $configBase = trim((string) config('amadeus.base_url', ''));
            $baseUrl = $resolved->baseUrl ?? ($configBase !== '' ? $configBase : null);
            $http = new SupplierJsonHttpClient($auth, AmadeusSelfServiceProvider::CODE, $baseUrl);

            return new AmadeusClient($auth, $http);
        });

        $this->app->singleton(IatiAuthService::class);
        $this->app->singleton(IatiClient::class, function ($app): IatiClient {
            $auth = $app->make(IatiAuthService::class);
            $resolved = $app->make(ProviderCredentialResolver::class)->forProvider('iati');
            $configBase = trim((string) config('iati.base_url', ''));
            $baseUrl = $resolved->baseUrl ?? ($configBase !== '' ? $configBase : null);
            $http = new SupplierJsonHttpClient($auth, 'iati', $baseUrl);

            return new IatiClient($auth, $http);
        });

        $this->app->singleton(DuffelAuthService::class);
        $this->app->singleton(DuffelClient::class, function ($app): DuffelClient {
            $auth = $app->make(DuffelAuthService::class);
            $resolved = $app->make(ProviderCredentialResolver::class)->forProvider('duffel', operation: 'search');
            $configBase = trim((string) config('duffel.base_url', ''));
            $baseUrl = $resolved->baseUrl ?? ($configBase !== '' ? $configBase : null);
            $http = new SupplierJsonHttpClient($auth, 'duffel', $baseUrl);

            return new DuffelClient($auth, $http);
        });
    }

    public function boot(): void
    {
        $settingsService = $this->app->make(SystemSettingsService::class);
        View::share('frontendFeatureFlags', [
            'group_ticketing_enabled' => $settingsService->getBool(
                key: 'app.feature_flags.group_ticketing_enabled',
                default: true,
                context: ['scope' => 'platform', 'category' => 'app']
            ),
            'umrah_packages_enabled' => $settingsService->getBool(
                key: 'app.feature_flags.umrah_packages_enabled',
                default: true,
                context: ['scope' => 'platform', 'category' => 'app']
            ),
        ]);

        RateLimiter::for('integrations-api', function (\Illuminate\Http\Request $request): Limit {
            $perMinute = max(1, (int) config('integrations.rate_limit_per_minute', 60));
            $identity = (string) $request->header('X-Integration-Key', $request->ip());

            return Limit::perMinute($perMinute)->by($identity);
        });

        $this->loadMigrationsFrom([
            database_path('migrations/_foundation'),
            database_path('migrations/_core'),
            database_path('migrations/_catalog'),
            database_path('migrations/_transactional_workflow'),
            database_path('migrations/_extensions_cms_audit'),
            database_path('migrations/_extensions_integrations'),
            database_path('migrations/_extensions_booking_commerce_crm'),
            database_path('migrations/_extensions_operational'),
            ...(app()->environment('testing') ? [database_path('migrations/_testing')] : []),
        ]);

        Gate::define('access-admin-area', function (User $user): bool {
            return $user->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
                UserRole::SALES_OPERATOR->value,
            ]);
        });

        Gate::define('access-agency-area', function (User $user): bool {
            return $user->hasAnyRole([UserRole::AGENCY_USER->value]);
        });

        Gate::define('permission', function (User $user, string $permission): bool {
            return $user->hasPermission($permission);
        });

        Gate::define('manage-tenancy-settings', function (User $user): bool {
            return $user->role === UserRole::SUPER_ADMIN;
        });
        Gate::define('manage-permission-matrix', function (User $user): bool {
            return $user->role === UserRole::SUPER_ADMIN;
        });

        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Inquiry::class, InquiryPolicy::class);

        Event::listen(
            InquiryFollowUpScheduled::class,
            TriggerInquiryFollowUpScheduledAutomation::class
        );
        Event::listen(
            BookingConfirmed::class,
            TriggerBookingConfirmedAutomation::class
        );
    }
}
