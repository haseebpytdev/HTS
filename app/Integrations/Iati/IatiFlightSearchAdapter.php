<?php

namespace App\Integrations\Iati;

use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightSearchRequestData;

final class IatiFlightSearchAdapter implements FlightSearchProviderInterface
{
    public function __construct(
        private readonly IatiClient $client,
    ) {
    }

    public function providerCode(): string
    {
        return 'iati';
    }

    public function searchFlights(FlightSearchRequestData $request): array
    {
        $this->client->baseUrl();

        return [];
    }
}
