<?php

namespace App\Integrations\Amadeus;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\Mappers\AmadeusFlightOfferMapper;
use App\Integrations\Amadeus\Payloads\AmadeusFlightSearchPayloadBuilder;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;

/**
 * Phase 11.8 — Amadeus flight search adapter (scaffold only).
 *
 * Next: Flight Offers Search via AmadeusFlightSearchPayloadBuilder and AmadeusClient::json(),
 * then AmadeusFlightOfferMapper::mapOffers().
 */
final class AmadeusFlightSearchAdapter implements FlightSearchProviderInterface
{
    public function __construct(
        private readonly AmadeusClient $client,
        private readonly AmadeusFlightOfferMapper $flightOfferMapper,
        private readonly AmadeusFlightSearchPayloadBuilder $payloadBuilder,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
    }

    public function searchFlights(FlightSearchRequestData $request): array
    {
        $query = $this->payloadBuilder->forSearch($request);
        $rawEnvelope = array_merge($this->stubRawSearchEnvelope($request), ['vendor_payload' => $query]);
        if ((bool) config('amadeus.live_enabled', false)) {
            $endpoint = (string) config('amadeus.endpoints.flight_search', '/v2/shopping/flight-offers');
            $correlationId = (string) Str::uuid();
            $startedAt = microtime(true);
            $response = $this->client->json()->get($endpoint, $query, [
                'X-Correlation-ID' => $correlationId,
            ]);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $rawEnvelope = $response->decodedJson;
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'search',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'GET',
                url: $this->buildUrl($endpoint),
                requestBody: ['query' => $query],
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'search')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));
        }

        return $this->flightOfferMapper->mapOffers($rawEnvelope);
    }

    /**
     * @return array<string, mixed>
     */
    private function stubRawSearchEnvelope(FlightSearchRequestData $request): array
    {
        return [
            '_scaffold' => 'amadeus.phase_11_8',
            'request_echo' => [
                'origin' => $request->origin,
                'destination' => $request->destination,
                'departure_date' => $request->departureDate,
                'adults' => $request->adults,
                'children' => $request->children,
                'infants' => $request->infants,
            ],
        ];
    }

    private function runtimeEnvironment(): string
    {
        return strtolower((string) config('integrations.credential_environment', 'production')) === 'test'
            ? 'sandbox'
            : 'production';
    }

    private function buildUrl(string $endpoint): string
    {
        $base = $this->client->baseUrl();
        if ($base === null || $base === '') {
            return $endpoint;
        }

        return rtrim($base, '/').'/'.ltrim($endpoint, '/');
    }
}
