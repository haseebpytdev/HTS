<?php

namespace App\Integrations\Duffel;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\Duffel\Mappers\DuffelPriceBreakdownMapper;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;

final class DuffelFlightPriceAdapter implements FlightPricingProviderInterface
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly DuffelPriceBreakdownMapper $priceMapper,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return 'duffel';
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
    {
        $correlationId = (string) ($opaqueContext['correlation_id'] ?? Str::uuid());
        $requestHeaders = ['X-Correlation-ID' => $correlationId];
        $pricingOfferReference = $this->resolvePricingOfferReference($offerReference, $opaqueContext);
        $query = [];
        $passengers = $this->resolveSelectedPassengers($opaqueContext);
        if ($passengers !== []) {
            $query['selected_passengers[]'] = $passengers;
        }
        if (isset($opaqueContext['return_available_services'])) {
            $query['return_available_services'] = $opaqueContext['return_available_services'] ? 'true' : 'false';
        }

        $startedAt = microtime(true);
        $response = $this->client->getOffer($pricingOfferReference, $query, $requestHeaders);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
            provider: $this->providerCode(),
            operation: 'pricing',
            environment: $this->runtimeEnvironment(),
            correlationId: $correlationId,
            httpMethod: 'GET',
            url: $this->buildUrl($this->client->config()->offersPath.'/'.$pricingOfferReference),
            requestHeaders: $requestHeaders,
            requestBody: [
                'offer_reference' => $pricingOfferReference,
                'query' => $query,
            ],
            integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'pricing')->integrationConnectionId,
            userId: auth()->id(),
            statusCode: $response->statusCode,
            responseHeaders: $response->headers,
            responseBody: $response->decodedJson,
            latencyMs: $latencyMs,
        ));

        return $this->priceMapper->mapPriceBreakdown($response->decodedJson);
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     */
    private function resolvePricingOfferReference(string $offerReference, array $opaqueContext): string
    {
        $selectedReference = trim((string) ($opaqueContext['selected_offer_reference'] ?? ''));
        if ($selectedReference !== '') {
            return $selectedReference;
        }

        $selectedOffer = is_array($opaqueContext['selected_offer'] ?? null) ? $opaqueContext['selected_offer'] : null;
        if ($selectedOffer === null) {
            return $offerReference;
        }

        foreach (['provider_offer_reference', 'offer_reference', 'id'] as $key) {
            $value = trim((string) ($selectedOffer[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $offerReference;
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     * @return list<string>
     */
    private function resolveSelectedPassengers(array $opaqueContext): array
    {
        $fromOpaque = $opaqueContext['passengers'] ?? null;
        if (is_array($fromOpaque)) {
            $normalized = array_values(array_filter(array_map(
                static fn (mixed $row): string => trim((string) $row),
                $fromOpaque
            ), static fn (string $row): bool => $row !== ''));

            if ($normalized !== []) {
                return $normalized;
            }
        }

        $selectedOffer = is_array($opaqueContext['selected_offer'] ?? null) ? $opaqueContext['selected_offer'] : null;
        if ($selectedOffer === null || ! is_array($selectedOffer['passengers'] ?? null)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $passenger): string {
                if (is_array($passenger)) {
                    return trim((string) ($passenger['id'] ?? $passenger['passenger_id'] ?? ''));
                }

                return trim((string) $passenger);
            },
            $selectedOffer['passengers']
        ), static fn (string $passenger): bool => $passenger !== ''));
    }

    private function runtimeEnvironment(): string
    {
        return strtolower((string) config('integrations.credential_environment', 'production')) === 'test'
            ? 'sandbox'
            : 'production';
    }

    private function buildUrl(string $endpoint): string
    {
        $base = $this->client->baseUrl() ?? $this->client->config()->baseUrl;
        if ($base === null || $base === '') {
            return $endpoint;
        }

        return rtrim($base, '/').'/'.ltrim($endpoint, '/');
    }
}
