<?php

namespace App\Integrations\Sabre;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Sabre\Mappers\SabreFlightOfferMapper;
use App\Integrations\Sabre\Payloads\SabreFlightSearchPayloadBuilder;
use App\Integrations\Sabre\Support\SabreSoapPayloadSanitizer;
use App\Integrations\Sabre\Support\SabreSoapResponseExtractor;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sabre REST/SOAP flight search adapter.
 */
final class SabreFlightSearchAdapter implements FlightSearchProviderInterface
{
    public function __construct(
        private readonly SabreClient $client,
        private readonly SabreFlightOfferMapper $flightOfferMapper,
        private readonly SabreFlightSearchPayloadBuilder $payloadBuilder,
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

    public function searchFlights(FlightSearchRequestData $request): array
    {
        try {
            $soapEnabled = (bool) config('sabre.soap_enabled', false);
            $restEnabled = $this->client->isRestSearchEnabled();
            if (! $soapEnabled && ! $restEnabled) {
                throw new SupplierIntegrationException(
                    message: 'Sabre search is disabled. Enable REST BFM search or SOAP search for Sabre.',
                    supplierCode: 'SABRE_SEARCH_DISABLED',
                    normalizedCode: 'integration_provider_unavailable',
                    apiError: new ApiErrorData(
                        code: 'integration_provider_unavailable',
                        message: 'Sabre search is disabled. Enable REST BFM search or SOAP search for Sabre.',
                        supplierCode: 'SABRE_SEARCH_DISABLED',
                        httpStatus: 503,
                    ),
                );
            }
            $payload = $this->payloadBuilder->forSearch($request);
            $rawEnvelope = array_merge($this->stubRawSearchEnvelope($request), ['vendor_payload' => $payload]);
            if ((bool) config('sabre.live_enabled', false) || $soapEnabled || (bool) config('sabre.rest_search_enabled', false)) {
                $correlationId = (string) Str::uuid();
                if ($soapEnabled) {
                    $endpoint = (string) config('sabre.endpoints.flight_search_soap', '/v3.3.0/OTA_AirLowFareSearchRQ');
                    $action = (string) config('sabre.endpoints.flight_search_soap_action', 'OTA_AirLowFareSearchRQ');
                    $xmlBody = $this->xmlEnvelopeFromPayload($payload);
                    $startedAt = microtime(true);
                    $response = $this->client->soap()->request($endpoint, $action, $xmlBody, [
                        'X-Correlation-ID' => $correlationId,
                    ]);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $operationData = $this->soapResponseExtractor->extractOperation($response->decodedJson, 'OTA_AirLowFareSearchRS');
                    $rawEnvelope = ['groupedItineraryResponse' => $operationData];
                    $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                        provider: $this->providerCode(),
                        operation: 'search_soap',
                        environment: $this->runtimeEnvironment(),
                        correlationId: $correlationId,
                        httpMethod: 'POST',
                        url: $this->buildUrl($endpoint),
                        requestHeaders: ['soap_action' => $action, 'x-correlation-id' => $correlationId],
                        requestBody: ['soap_xml' => $this->soapPayloadSanitizer->sanitizeXml($xmlBody)],
                        integrationConnectionId: $this->credentialResolver->forProvider('sabre', operation: 'search')->integrationConnectionId,
                        userId: auth()->id(),
                        statusCode: $response->statusCode,
                        responseHeaders: $response->headers,
                        responseBody: $this->soapPayloadSanitizer->sanitizeArray($response->decodedJson),
                        latencyMs: $latencyMs,
                    ));
                } else {
                    if (! $restEnabled) {
                        throw new SupplierIntegrationException(
                            message: 'Sabre REST BFM search is disabled.',
                            supplierCode: 'SABRE_BFM_REST_DISABLED',
                            normalizedCode: 'integration_provider_unavailable',
                            apiError: new ApiErrorData(
                                code: 'integration_provider_unavailable',
                                message: 'Sabre REST BFM search is disabled.',
                                supplierCode: 'SABRE_BFM_REST_DISABLED',
                                httpStatus: 503,
                            ),
                        );
                    }
                    $endpoint = (string) config('sabre.endpoints.flight_search', '/v5/offers/shop');
                    $startedAt = microtime(true);
                    try {
                        $response = $this->client->bargainFinderMaxSearch($payload, [
                            'X-Correlation-ID' => $correlationId,
                        ]);
                    } catch (Throwable $transportOrValidationError) {
                        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                        $statusCode = null;
                        $responseBody = [];
                        if ($transportOrValidationError instanceof ProviderValidationException) {
                            $statusCode = (int) ($transportOrValidationError->supplierContext['status_code'] ?? 0);
                            $responseBody = $this->extractErrorResponseBody($transportOrValidationError->supplierContext['body'] ?? null);
                        }

                        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                            provider: $this->providerCode(),
                            operation: 'search_bfm_v5',
                            environment: $this->runtimeEnvironment(),
                            correlationId: $correlationId,
                            httpMethod: 'POST',
                            url: $this->buildUrl($endpoint),
                            requestHeaders: ['x-correlation-id' => $correlationId],
                            requestBody: $this->soapPayloadSanitizer->sanitizeArray($payload),
                            integrationConnectionId: $this->credentialResolver->forProvider('sabre', operation: 'search')->integrationConnectionId,
                            userId: auth()->id(),
                            statusCode: $statusCode,
                            responseBody: $this->soapPayloadSanitizer->sanitizeArray($responseBody),
                            latencyMs: $latencyMs,
                            errorCategory: 'failed',
                        ));

                        throw $transportOrValidationError;
                    }
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $rawEnvelope = $response->decodedJson;
                    $hasItineraryGroups = $this->hasGroupedItineraryGroups($rawEnvelope);
                    $errorCategory = $hasItineraryGroups ? null : 'successful_empty';
                    $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                        provider: $this->providerCode(),
                        operation: 'search_bfm_v5',
                        environment: $this->runtimeEnvironment(),
                        correlationId: $correlationId,
                        httpMethod: 'POST',
                        url: $this->buildUrl($endpoint),
                        requestHeaders: ['x-correlation-id' => $correlationId],
                        requestBody: $this->soapPayloadSanitizer->sanitizeArray($payload),
                        integrationConnectionId: $this->credentialResolver->forProvider('sabre', operation: 'search')->integrationConnectionId,
                        userId: auth()->id(),
                        statusCode: $response->statusCode,
                        responseHeaders: $response->headers,
                        responseBody: $this->augmentRestResponseForLogs($response->decodedJson, $hasItineraryGroups),
                        latencyMs: $latencyMs,
                        errorCategory: $errorCategory,
                    ));
                }
            }

            if (! $this->hasGroupedItineraryGroups($rawEnvelope)) {
                return [];
            }

            return $this->flightOfferMapper->mapOffers($rawEnvelope);
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stubRawSearchEnvelope(FlightSearchRequestData $request): array
    {
        return [
            '_scaffold' => 'sabre.phase_11_7',
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function xmlEnvelopeFromPayload(array $payload): string
    {
        $origin = (string) data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.OriginLocation.LocationCode', '');
        $destination = (string) data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.DestinationLocation.LocationCode', '');
        $departure = (string) data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.DepartureDateTime', '');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
  <soapenv:Header/>
  <soapenv:Body>
    <ota:OTA_AirLowFareSearchRQ>
      <ota:OriginDestinationInformation>
        <ota:DepartureDateTime>{$departure}</ota:DepartureDateTime>
        <ota:OriginLocation LocationCode="{$origin}"/>
        <ota:DestinationLocation LocationCode="{$destination}"/>
      </ota:OriginDestinationInformation>
    </ota:OTA_AirLowFareSearchRQ>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function normalizeError(Throwable $e): SupplierIntegrationException
    {
        if ($e instanceof SupplierIntegrationException) {
            return $e;
        }
        if ($e instanceof ProviderAuthException) {
            $status = (int) ($e->httpStatus ?? 401);
            return new SupplierIntegrationException(
                message: 'Sabre authentication failed.',
                supplierCode: 'SABRE_AUTH_FAILED',
                normalizedCode: 'supplier_auth_failed',
                apiError: new ApiErrorData('supplier_auth_failed', 'Sabre authentication failed.', 'SABRE_AUTH_FAILED', httpStatus: $status),
                previous: $e,
            );
        }
        if ($e instanceof ProviderValidationException) {
            $status = (int) ($e->supplierContext['status_code'] ?? 422);
            if (in_array($status, [401, 403], true)) {
                return new SupplierIntegrationException(
                    message: 'Sabre authentication failed.',
                    supplierCode: 'SABRE_AUTH_FAILED',
                    normalizedCode: 'supplier_auth_failed',
                    apiError: new ApiErrorData('supplier_auth_failed', 'Sabre authentication failed.', 'SABRE_AUTH_FAILED', httpStatus: $status),
                    previous: $e,
                );
            }
            $httpStatus = $status === 400 ? 400 : 422;

            return new SupplierIntegrationException(
                message: 'Sabre rejected the search request.',
                supplierCode: 'SABRE_REQUEST_INVALID',
                normalizedCode: 'supplier_request_invalid',
                supplierContext: $e->supplierContext,
                apiError: new ApiErrorData('supplier_request_invalid', 'Sabre rejected the search request.', 'SABRE_REQUEST_INVALID', httpStatus: $httpStatus),
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
                apiError: new ApiErrorData('supplier_transport_error', 'Sabre transport error.', 'SABRE_TRANSPORT_ERROR', httpStatus: 500),
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
        $base = $this->client->baseUrl();
        if ($base === null || $base === '') {
            return $endpoint;
        }

        return rtrim($base, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $rawEnvelope
     */
    private function hasGroupedItineraryGroups(array $rawEnvelope): bool
    {
        $groups = data_get($rawEnvelope, 'groupedItineraryResponse.itineraryGroups');

        return is_array($groups) && $groups !== [];
    }

    /**
     * @param  array<string, mixed>  $rawEnvelope
     * @return array<string, mixed>
     */
    private function augmentRestResponseForLogs(array $rawEnvelope, bool $hasItineraryGroups): array
    {
        $sanitized = $this->soapPayloadSanitizer->sanitizeArray($rawEnvelope);
        $sabremessages = data_get($rawEnvelope, 'groupedItineraryResponse.messages', []);
        if (! is_array($sabremessages)) {
            $sabremessages = [];
        }

        $classification = $hasItineraryGroups ? 'successful_with_offers' : 'successful_empty';

        return array_merge($sanitized, [
            '_search_classification' => $classification,
            '_sabre_messages' => $sabremessages,
            '_itinerary_count' => (int) data_get($rawEnvelope, 'groupedItineraryResponse.statistics.itineraryCount', 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractErrorResponseBody(mixed $body): array
    {
        if (is_array($body)) {
            return $body;
        }
        if (! is_string($body) || trim($body) === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded)
            ? $decoded
            : ['raw' => mb_substr($body, 0, 2000)];
    }
}
