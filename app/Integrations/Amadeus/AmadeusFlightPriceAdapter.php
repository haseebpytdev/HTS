<?php

namespace App\Integrations\Amadeus;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\Mappers\AmadeusPriceBreakdownMapper;
use App\Integrations\Amadeus\Support\AmadeusOfferReferenceCodec;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;

/**
 * Phase 11.8 — Amadeus fare revalidation (scaffold only).
 *
 * Next: Flight Offers Price / confirm pricing; {@see AmadeusPriceBreakdownMapper::mapPriceBreakdown()}.
 */
final class AmadeusFlightPriceAdapter implements FlightPricingProviderInterface
{
    public function __construct(
        private readonly AmadeusClient $client,
        private readonly AmadeusPriceBreakdownMapper $priceMapper,
        private readonly AmadeusOfferReferenceCodec $codec,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
    {
        $raw = $this->stubRawPriceEnvelope($offerReference, $opaqueContext);
        if ((bool) config('amadeus.live_enabled', false)) {
            $endpoint = (string) config('amadeus.endpoints.flight_pricing', '/v1/shopping/flight-offers/pricing');
            $payload = $this->pricingPayload($offerReference, $opaqueContext);
            if ($payload !== null) {
                $correlationId = (string) Str::uuid();
                $startedAt = microtime(true);
                $response = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => $correlationId,
                ]);
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                $raw = $response->decodedJson;
                $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                    provider: $this->providerCode(),
                    operation: 'pricing',
                    environment: $this->runtimeEnvironment(),
                    correlationId: $correlationId,
                    httpMethod: 'POST',
                    url: $this->buildUrl($endpoint),
                    requestBody: $payload,
                    integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'pricing')->integrationConnectionId,
                    userId: auth()->id(),
                    statusCode: $response->statusCode,
                    responseHeaders: $response->headers,
                    responseBody: $response->decodedJson,
                    latencyMs: $latencyMs,
                ));
            }
        }

        return $this->priceMapper->mapPriceBreakdown($raw);
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     * @return array<string, mixed>
     */
    private function stubRawPriceEnvelope(string $offerReference, array $opaqueContext): array
    {
        return [
            '_scaffold' => 'amadeus.phase_11_8',
            'offer_reference' => $offerReference,
            'opaque_context' => $opaqueContext,
        ];
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     * @return array<string, mixed>|null
     */
    private function pricingPayload(string $offerReference, array $opaqueContext): ?array
    {
        $offer = $this->codec->decode($offerReference);
        if ($offer === null) {
            $fromContext = $opaqueContext['provider_offer'] ?? null;
            $offer = is_array($fromContext) ? $fromContext : null;
        }
        if ($offer === null) {
            return null;
        }

        return [
            'data' => [
                'type' => 'flight-offers-pricing',
                'flightOffers' => [$offer],
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
