<?php

namespace App\Integrations\Travelport;

use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Travelport\Mappers\TravelportFlightOfferMapper;
use App\Integrations\Travelport\Payloads\TravelportFlightSearchPayloadBuilder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 11.6 — Travelport flight search adapter (scaffold only).
 *
 * Next: build {@see TravelportFlightSearchPayloadBuilder}, POST via {@see TravelportClient::json()},
 * pass {@see SupplierHttpResponse::decodedJson} into {@see TravelportFlightOfferMapper::mapOffers()}.
 */
final class TravelportFlightSearchAdapter implements FlightSearchProviderInterface
{
    public function __construct(
        private readonly TravelportClient $client,
        private readonly TravelportFlightOfferMapper $flightOfferMapper,
        private readonly TravelportFlightSearchPayloadBuilder $payloadBuilder,
    ) {
    }

    public function providerCode(): string
    {
        return 'travelport';
    }

    public function searchFlights(FlightSearchRequestData $request): array
    {
        try {
            $payload = $this->payloadBuilder->forSearch($request);
            $rawEnvelope = array_merge($this->stubRawSearchEnvelope($request), ['vendor_payload' => $payload]);
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = (string) config('travelport.endpoints.flight_search', '/11/air/search');
                $rawEnvelope = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => (string) Str::uuid(),
                ])->decodedJson;
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
            '_scaffold' => 'travelport.phase_11_6',
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

    private function normalizeError(Throwable $e): SupplierIntegrationException
    {
        if ($e instanceof SupplierIntegrationException) {
            return $e;
        }

        if ($e instanceof ProviderAuthException) {
            return new SupplierIntegrationException(
                message: 'Travelport authentication failed.',
                supplierCode: 'TRAVELPORT_AUTH_FAILED',
                normalizedCode: 'supplier_auth_failed',
                apiError: new ApiErrorData('supplier_auth_failed', 'Travelport authentication failed.', 'TRAVELPORT_AUTH_FAILED', httpStatus: 401),
                previous: $e,
            );
        }
        if ($e instanceof ProviderValidationException) {
            return new SupplierIntegrationException(
                message: 'Travelport rejected the request payload.',
                supplierCode: 'TRAVELPORT_REQUEST_INVALID',
                normalizedCode: 'supplier_request_invalid',
                supplierContext: $e->supplierContext,
                apiError: new ApiErrorData('supplier_request_invalid', 'Travelport rejected the request payload.', 'TRAVELPORT_REQUEST_INVALID', httpStatus: 422),
                previous: $e,
            );
        }
        if ($e instanceof ProviderRateLimitException) {
            return new SupplierIntegrationException(
                message: 'Travelport rate limit exceeded.',
                supplierCode: 'TRAVELPORT_RATE_LIMIT',
                normalizedCode: 'supplier_rate_limited',
                apiError: new ApiErrorData('supplier_rate_limited', 'Travelport rate limit exceeded.', 'TRAVELPORT_RATE_LIMIT', httpStatus: 429),
                previous: $e,
            );
        }
        if ($e instanceof ProviderTransportException) {
            return new SupplierIntegrationException(
                message: 'Travelport transport error.',
                supplierCode: 'TRAVELPORT_TRANSPORT_ERROR',
                normalizedCode: 'supplier_transport_error',
                apiError: new ApiErrorData('supplier_transport_error', 'Travelport transport error.', 'TRAVELPORT_TRANSPORT_ERROR', httpStatus: 502),
                previous: $e,
            );
        }

        return new SupplierIntegrationException(
            message: $e->getMessage(),
            supplierCode: 'TRAVELPORT_UNKNOWN_ERROR',
            normalizedCode: 'integration_error',
            apiError: new ApiErrorData('integration_error', $e->getMessage(), 'TRAVELPORT_UNKNOWN_ERROR', httpStatus: 502),
            previous: $e,
        );
    }
}
