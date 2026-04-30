<?php

namespace App\Integrations\Travelport;

use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Travelport\Mappers\TravelportPriceBreakdownMapper;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 11.6 — Travelport fare revalidation / price check (scaffold only).
 *
 * Next: POST reprice endpoint; map {@see SupplierHttpResponse::decodedJson} with {@see TravelportPriceBreakdownMapper}.
 */
final class TravelportFlightPriceAdapter implements FlightPricingProviderInterface
{
    public function __construct(
        private readonly TravelportClient $client,
        private readonly TravelportPriceBreakdownMapper $priceMapper,
    ) {
    }

    public function providerCode(): string
    {
        return 'travelport';
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
    {
        try {
            $raw = $this->stubRawPriceEnvelope($offerReference, $opaqueContext);
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = (string) config('travelport.endpoints.flight_pricing', '/11/air/price');
                $payload = [
                    'offer_reference' => $offerReference,
                    'opaque_context' => $opaqueContext,
                ];
                $raw = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => (string) Str::uuid(),
                ])->decodedJson;
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
            '_scaffold' => 'travelport.phase_11_6',
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
                message: 'Travelport authentication failed.',
                supplierCode: 'TRAVELPORT_AUTH_FAILED',
                normalizedCode: 'supplier_auth_failed',
                apiError: new ApiErrorData('supplier_auth_failed', 'Travelport authentication failed.', 'TRAVELPORT_AUTH_FAILED', httpStatus: 401),
                previous: $e,
            );
        }
        if ($e instanceof ProviderValidationException) {
            return new SupplierIntegrationException(
                message: 'Travelport rejected pricing request.',
                supplierCode: 'TRAVELPORT_PRICING_INVALID',
                normalizedCode: 'supplier_request_invalid',
                supplierContext: $e->supplierContext,
                apiError: new ApiErrorData('supplier_request_invalid', 'Travelport rejected pricing request.', 'TRAVELPORT_PRICING_INVALID', httpStatus: 422),
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
