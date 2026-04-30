<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\AmadeusAuthService;
use App\Integrations\Amadeus\AmadeusBookingAdapter;
use App\Integrations\Amadeus\AmadeusFlightPriceAdapter;
use App\Integrations\Amadeus\AmadeusFlightSearchAdapter;
use App\Integrations\Duffel\DuffelAuthService;
use App\Integrations\Duffel\DuffelBookingAdapter;
use App\Integrations\Duffel\DuffelFlightPriceAdapter;
use App\Integrations\Duffel\DuffelFlightSearchAdapter;
use App\Integrations\Iati\IatiAuthService;
use App\Integrations\Iati\IatiBookingAdapter;
use App\Integrations\Iati\IatiFlightPriceAdapter;
use App\Integrations\Iati\IatiFlightSearchAdapter;
use App\Integrations\Sabre\SabreAuthService;
use App\Integrations\Sabre\SabreBookingAdapter;
use App\Integrations\Sabre\SabreFlightPriceAdapter;
use App\Integrations\Sabre\SabreFlightSearchAdapter;
use App\Integrations\Stub\StubAuthTokenAdapter;
use App\Integrations\Stub\StubBookingAdapter;
use App\Integrations\Stub\StubFlightPriceAdapter;
use App\Integrations\Stub\StubFlightSearchAdapter;
use App\Integrations\Travelport\TravelportAuthService;
use App\Integrations\Travelport\TravelportBookingAdapter;
use App\Integrations\Travelport\TravelportFlightPriceAdapter;
use App\Integrations\Travelport\TravelportFlightSearchAdapter;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class IntegrationProviderRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function flightSearch(string $driver): FlightSearchProviderInterface
    {
        $driver = AmadeusSelfServiceProvider::normalize($driver);

        return match ($driver) {
            'travelport' => $this->container->make(TravelportFlightSearchAdapter::class),
            'sabre' => $this->container->make(SabreFlightSearchAdapter::class),
            AmadeusSelfServiceProvider::CODE => $this->container->make(AmadeusFlightSearchAdapter::class),
            'iati' => $this->container->make(IatiFlightSearchAdapter::class),
            'duffel' => $this->container->make(DuffelFlightSearchAdapter::class),
            'stub' => $this->container->make(StubFlightSearchAdapter::class),
            default => throw new InvalidArgumentException('Unknown flight search driver: '.$driver),
        };
    }

    public function flightPricing(string $driver): FlightPricingProviderInterface
    {
        $driver = AmadeusSelfServiceProvider::normalize($driver);

        return match ($driver) {
            'travelport' => $this->container->make(TravelportFlightPriceAdapter::class),
            'sabre' => $this->container->make(SabreFlightPriceAdapter::class),
            AmadeusSelfServiceProvider::CODE => $this->container->make(AmadeusFlightPriceAdapter::class),
            'iati' => $this->container->make(IatiFlightPriceAdapter::class),
            'duffel' => $this->container->make(DuffelFlightPriceAdapter::class),
            'stub' => $this->container->make(StubFlightPriceAdapter::class),
            default => throw new InvalidArgumentException('Unknown flight pricing driver: '.$driver),
        };
    }

    public function booking(string $driver): BookingProviderInterface
    {
        $driver = AmadeusSelfServiceProvider::normalize($driver);

        return match ($driver) {
            'travelport' => $this->container->make(TravelportBookingAdapter::class),
            'sabre' => $this->container->make(SabreBookingAdapter::class),
            AmadeusSelfServiceProvider::CODE => $this->container->make(AmadeusBookingAdapter::class),
            'iati' => $this->container->make(IatiBookingAdapter::class),
            'duffel' => $this->container->make(DuffelBookingAdapter::class),
            'stub' => $this->container->make(StubBookingAdapter::class),
            default => throw new InvalidArgumentException('Unknown booking driver: '.$driver),
        };
    }

    public function auth(string $driver): AuthTokenProviderInterface
    {
        $driver = AmadeusSelfServiceProvider::normalize($driver);

        return match ($driver) {
            'travelport' => $this->container->make(TravelportAuthService::class),
            'sabre' => $this->container->make(SabreAuthService::class),
            AmadeusSelfServiceProvider::CODE => $this->container->make(AmadeusAuthService::class),
            'iati' => $this->container->make(IatiAuthService::class),
            'duffel' => $this->container->make(DuffelAuthService::class),
            'stub' => $this->container->make(StubAuthTokenAdapter::class),
            default => throw new InvalidArgumentException('Unknown auth driver: '.$driver),
        };
    }
}
