<?php

namespace App\Integrations\Sabre;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Sabre\Mappers\SabrePriceBreakdownMapper;
use App\Integrations\Sabre\Payloads\SabreFlightPricingSoapPayloadBuilder;
use App\Integrations\Sabre\Support\SabreSoapPayloadSanitizer;
use App\Integrations\Sabre\Support\SabreSoapResponseExtractor;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 11.7 — Sabre fare revalidation (scaffold only).
 *
 * Next: Sabre reprice REST; SabrePriceBreakdownMapper::mapPriceBreakdown().
 */
final class SabreFlightPriceAdapter implements FlightPricingProviderInterface
{
    public function __construct(
        private readonly SabreClient $client,
        private readonly SabrePriceBreakdownMapper $priceMapper,
        private readonly SabreFlightPricingSoapPayloadBuilder $soapPayloadBuilder,
        private readonly SabreSoapResponseExtractor $soapResponseExtractor,
        private readonly SabreSoapPayloadSanitizer $soapPayloadSanitizer,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return 'sabre';
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
    {
        try {
            $raw = $this->stubRawPriceEnvelope($offerReference, $opaqueContext);
            if ((bool) config('sabre.live_enabled', false)) {
                $correlationId = (string) Str::uuid();
                if ((bool) config('sabre.soap_enabled', false)) {
                    $endpoint = (string) config('sabre.endpoints.flight_pricing_soap', '/v3.3.0/OTA_AirPriceRQ');
                    $action = (string) config('sabre.endpoints.flight_pricing_soap_action', 'OTA_AirPriceRQ');
                    $xmlBody = $this->soapPayloadBuilder->buildEnvelope($offerReference, $opaqueContext);
                    $startedAt = microtime(true);
                    $response = $this->client->soap()->request($endpoint, $action, $xmlBody, [
                        'X-Correlation-ID' => $correlationId,
                    ]);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $operationData = $this->soapResponseExtractor->extractOperation($response->decodedJson, 'OTA_AirPriceRS');
                    $raw = ['OTA_AirPriceRS' => $operationData];
                    $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                        provider: $this->providerCode(),
                        operation: 'pricing_soap',
                        environment: $this->runtimeEnvironment(),
                        correlationId: $correlationId,
                        httpMethod: 'POST',
                        url: $this->buildUrl($endpoint),
                        requestHeaders: ['soap_action' => $action, 'x-correlation-id' => $correlationId],
                        requestBody: ['soap_xml' => $this->soapPayloadSanitizer->sanitizeXml($xmlBody)],
                        integrationConnectionId: $this->credentialResolver->forProvider('sabre', operation: 'pricing')->integrationConnectionId,
                        userId: auth()->id(),
                        statusCode: $response->statusCode,
                        responseHeaders: $response->headers,
                        responseBody: $this->soapPayloadSanitizer->sanitizeArray($response->decodedJson),
                        latencyMs: $latencyMs,
                    ));
                } else {
                    $endpoint = (string) config('sabre.endpoints.flight_pricing', '/v4/offers/price');
                    $payload = [
                        'offer_reference' => $offerReference,
                        'opaque_context' => $opaqueContext,
                    ];
                    $raw = $this->client->json()->post($endpoint, $payload, [
                        'X-Correlation-ID' => $correlationId,
                    ])->decodedJson;
                }
            }

            return $this->priceMapper->mapPriceBreakdown($raw);
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     * @return array<string, mixed>
     */
    private function stubRawPriceEnvelope(string $offerReference, array $opaqueContext): array
    {
        return [
            '_scaffold' => 'sabre.phase_11_7',
            'offer_reference' => $offerReference,
            'opaque_context' => $opaqueContext,
        ];
    }

    private function normalizeError(Throwable $e): SupplierIntegrationException
    {
        if ($e instanceof SupplierIntegrationException) {
            return $e;
        }
        if ($e instanceof ProviderAuthException) {
            return new SupplierIntegrationException(
                message: 'Sabre authentication failed.',
                supplierCode: 'SABRE_AUTH_FAILED',
                normalizedCode: 'supplier_auth_failed',
                apiError: new ApiErrorData('supplier_auth_failed', 'Sabre authentication failed.', 'SABRE_AUTH_FAILED', httpStatus: 401),
                previous: $e,
            );
        }
        if ($e instanceof ProviderValidationException) {
            return new SupplierIntegrationException(
                message: 'Sabre rejected pricing request.',
                supplierCode: 'SABRE_PRICING_INVALID',
                normalizedCode: 'supplier_request_invalid',
                supplierContext: $e->supplierContext,
                apiError: new ApiErrorData('supplier_request_invalid', 'Sabre rejected pricing request.', 'SABRE_PRICING_INVALID', httpStatus: 422),
                previous: $e,
            );
        }
        if ($e instanceof ProviderRateLimitException) {
            return new SupplierIntegrationException(
                message: 'Sabre rate limit exceeded.',
                supplierCode: 'SABRE_RATE_LIMIT',
                normalizedCode: 'supplier_rate_limited',
                apiError: new ApiErrorData('supplier_rate_limited', 'Sabre rate limit exceeded.', 'SABRE_RATE_LIMIT', httpStatus: 429),
                previous: $e,
            );
        }
        if ($e instanceof ProviderTransportException) {
            return new SupplierIntegrationException(
                message: 'Sabre transport error.',
                supplierCode: 'SABRE_TRANSPORT_ERROR',
                normalizedCode: 'supplier_transport_error',
                apiError: new ApiErrorData('supplier_transport_error', 'Sabre transport error.', 'SABRE_TRANSPORT_ERROR', httpStatus: 502),
                previous: $e,
            );
        }

        return new SupplierIntegrationException(
            message: $e->getMessage(),
            supplierCode: 'SABRE_UNKNOWN_ERROR',
            normalizedCode: 'integration_error',
            apiError: new ApiErrorData('integration_error', $e->getMessage(), 'SABRE_UNKNOWN_ERROR', httpStatus: 502),
            previous: $e,
        );
    }

    private function runtimeEnvironment(): string
    {
        return strtolower((string) config('integrations.credential_environment', 'production')) === 'test'
            ? 'sandbox'
            : 'production';
    }

    private function buildUrl(string $endpoint): string
    {
        $base = $this->client->soap()->baseUrl() ?? $this->client->baseUrl();
        if ($base === null || $base === '') {
            return $endpoint;
        }

        return rtrim($base, '/').'/'.ltrim($endpoint, '/');
    }
}
