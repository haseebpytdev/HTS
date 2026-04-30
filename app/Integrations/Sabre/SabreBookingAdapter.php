<?php

namespace App\Integrations\Sabre;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\BookingAmendmentProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\BookingTicketingProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Sabre\Mappers\SabreBookingMapper;
use App\Integrations\Sabre\Payloads\SabreBookingSoapPayloadBuilder;
use App\Integrations\Sabre\Support\SabreSoapPayloadSanitizer;
use App\Integrations\Sabre\Support\SabreSoapResponseExtractor;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 11.7 — Sabre booking adapter (scaffold only).
 *
 * Next: create / retrieve / cancel Sabre REST; SabreBookingMapper::mapBooking().
 */
final class SabreBookingAdapter implements BookingProviderInterface, BookingTicketingProviderInterface, BookingAmendmentProviderInterface
{
    public function __construct(
        private readonly SabreClient $client,
        private readonly SabreBookingMapper $bookingMapper,
        private readonly SabreBookingSoapPayloadBuilder $soapPayloadBuilder,
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

    public function createBooking(BookingCreateRequestData $request): BookingData
    {
        try {
            if ((bool) config('sabre.live_enabled', false)) {
                $correlationId = (string) Str::uuid();
                if ((bool) config('sabre.soap_enabled', false)) {
                    $endpoint = (string) config('sabre.endpoints.flight_booking_soap', '/v2.5.0/CreatePassengerNameRecordRQ');
                    $action = (string) config('sabre.endpoints.flight_booking_soap_action', 'CreatePassengerNameRecordRQ');
                    $xmlBody = $this->soapPayloadBuilder->buildCreateBookingEnvelope($request);
                    $startedAt = microtime(true);
                    $response = $this->client->soap()->request($endpoint, $action, $xmlBody, [
                        'X-Correlation-ID' => $correlationId,
                    ]);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $operationData = $this->soapResponseExtractor->extractOperation($response->decodedJson, 'CreatePassengerNameRecordRS');
                    $raw = ['CreatePassengerNameRecordRS' => $operationData];
                    $this->logSoapExchange(
                        operation: 'booking_soap_create',
                        endpoint: $endpoint,
                        action: $action,
                        correlationId: $correlationId,
                        xmlBody: $xmlBody,
                        response: $response,
                        latencyMs: $latencyMs,
                    );

                    return $this->bookingMapper->mapBooking($raw);
                }

                $endpoint = (string) config('sabre.endpoints.flight_booking', '/v3/offers/book');
                $payload = [
                    'offer_reference' => $request->offerReference,
                    'travelers' => array_map(static fn ($traveler) => $traveler->jsonSerialize(), $request->travelers),
                    'contact_email' => $request->contactEmail,
                    'contact_phone' => $request->contactPhone,
                ];
                $raw = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => $correlationId,
                ])->decodedJson;

                return $this->bookingMapper->mapBooking($raw);
            }

            return new BookingData(
                status: 'not_implemented',
                providerCode: $this->providerCode(),
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    public function retrieveBooking(string $bookingReference): BookingData
    {
        try {
            if ((bool) config('sabre.live_enabled', false)) {
                $correlationId = (string) Str::uuid();
                if ((bool) config('sabre.soap_enabled', false)) {
                    $endpoint = (string) config('sabre.endpoints.flight_booking_retrieve_soap', '/v1.19.0/GetReservationRQ');
                    $action = (string) config('sabre.endpoints.flight_booking_retrieve_soap_action', 'GetReservationRQ');
                    $xmlBody = $this->soapPayloadBuilder->buildRetrieveEnvelope($bookingReference);
                    $startedAt = microtime(true);
                    $response = $this->client->soap()->request($endpoint, $action, $xmlBody, [
                        'X-Correlation-ID' => $correlationId,
                    ]);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $operationData = $this->soapResponseExtractor->extractOperation($response->decodedJson, 'GetReservationRS');
                    $raw = ['booking' => $operationData];
                    $this->logSoapExchange(
                        operation: 'booking_soap_retrieve',
                        endpoint: $endpoint,
                        action: $action,
                        correlationId: $correlationId,
                        xmlBody: $xmlBody,
                        response: $response,
                        latencyMs: $latencyMs,
                    );

                    return $this->bookingMapper->mapBooking($raw);
                }

                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('sabre.endpoints.flight_booking_retrieve', '/v3/bookings/{id}'));
                $raw = $this->client->json()->get($endpoint, [], [
                    'X-Correlation-ID' => $correlationId,
                ])->decodedJson;

                return $this->bookingMapper->mapBooking($raw);
            }

            return new BookingData(
                status: 'not_found',
                providerCode: $this->providerCode(),
                bookingReference: $bookingReference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        try {
            if ((bool) config('sabre.live_enabled', false)) {
                $correlationId = (string) Str::uuid();
                if ((bool) config('sabre.soap_enabled', false)) {
                    $endpoint = (string) config('sabre.endpoints.flight_booking_cancel_soap', '/v1.0.0/CancelReservationRQ');
                    $action = (string) config('sabre.endpoints.flight_booking_cancel_soap_action', 'CancelReservationRQ');
                    $xmlBody = $this->soapPayloadBuilder->buildCancelEnvelope($bookingReference);
                    $startedAt = microtime(true);
                    $response = $this->client->soap()->request($endpoint, $action, $xmlBody, [
                        'X-Correlation-ID' => $correlationId,
                    ]);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $operationData = $this->soapResponseExtractor->extractOperation($response->decodedJson, 'CancelReservationRS');
                    $raw = ['booking' => array_merge(is_array($operationData) ? $operationData : [], ['status' => 'cancelled', 'bookingReference' => $bookingReference])];
                    $this->logSoapExchange(
                        operation: 'booking_soap_cancel',
                        endpoint: $endpoint,
                        action: $action,
                        correlationId: $correlationId,
                        xmlBody: $xmlBody,
                        response: $response,
                        latencyMs: $latencyMs,
                    );

                    return $this->bookingMapper->mapBooking($raw);
                }

                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('sabre.endpoints.flight_booking_cancel', '/v3/bookings/{id}'));
                $raw = $this->client->json()->delete($endpoint, [], [
                    'X-Correlation-ID' => $correlationId,
                ])->decodedJson;

                return $this->bookingMapper->mapBooking($raw);
            }

            return new BookingData(
                status: 'not_implemented',
                providerCode: $this->providerCode(),
                bookingReference: $bookingReference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    public function ticketBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        try {
            if ((bool) config('sabre.live_enabled', false)) {
                $correlationId = (string) ($opaqueContext['correlation_id'] ?? Str::uuid());
                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('sabre.endpoints.flight_booking_ticket', '/v3/bookings/{id}/ticket'));
                $payload = [
                    'booking_reference' => $bookingReference,
                    'context' => $opaqueContext,
                ];
                $raw = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => $correlationId,
                ])->decodedJson;
                $mapped = $this->bookingMapper->mapBooking($raw);

                return new BookingData(
                    status: 'ticketed',
                    providerCode: $mapped->providerCode,
                    bookingReference: $mapped->bookingReference ?? $bookingReference,
                    pnr: $mapped->pnr,
                    travelers: $mapped->travelers,
                    totalPrice: $mapped->totalPrice,
                    createdAt: $mapped->createdAt,
                    metadata: $mapped->metadata,
                );
            }

            return new BookingData(
                status: 'ticketed',
                providerCode: $this->providerCode(),
                bookingReference: $bookingReference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    public function amendBooking(string $bookingReference, array $amendmentPayload = []): BookingData
    {
        try {
            if ((bool) config('sabre.live_enabled', false)) {
                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('sabre.endpoints.flight_booking_amend', '/v3/bookings/{id}/change'));
                $payload = [
                    'booking_reference' => $bookingReference,
                    'changes' => $amendmentPayload,
                ];
                $raw = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => (string) Str::uuid(),
                ])->decodedJson;

                return $this->bookingMapper->mapBooking($raw);
            }

            return new BookingData(
                status: 'amended',
                providerCode: $this->providerCode(),
                bookingReference: $bookingReference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
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
                message: 'Sabre rejected booking request.',
                supplierCode: 'SABRE_BOOKING_INVALID',
                normalizedCode: 'supplier_request_invalid',
                supplierContext: $e->supplierContext,
                apiError: new ApiErrorData('supplier_request_invalid', 'Sabre rejected booking request.', 'SABRE_BOOKING_INVALID', httpStatus: 422),
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

    private function logSoapExchange(
        string $operation,
        string $endpoint,
        string $action,
        string $correlationId,
        string $xmlBody,
        \App\Data\Integrations\SupplierHttpResponse $response,
        int $latencyMs,
    ): void {
        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
            provider: $this->providerCode(),
            operation: $operation,
            environment: $this->runtimeEnvironment(),
            correlationId: $correlationId,
            httpMethod: 'POST',
            url: $this->buildUrl($endpoint),
            requestHeaders: ['soap_action' => $action, 'x-correlation-id' => $correlationId],
            requestBody: ['soap_xml' => $this->soapPayloadSanitizer->sanitizeXml($xmlBody)],
            integrationConnectionId: $this->credentialResolver->forProvider('sabre', operation: 'booking')->integrationConnectionId,
            userId: auth()->id(),
            statusCode: $response->statusCode,
            responseHeaders: $response->headers,
            responseBody: $this->soapPayloadSanitizer->sanitizeArray($response->decodedJson),
            latencyMs: $latencyMs,
        ));
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
