<?php

namespace App\Integrations\Travelport;

use App\Contracts\Integrations\BookingAmendmentProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\BookingTicketingProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Travelport\Mappers\TravelportBookingMapper;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 11.6 — Travelport booking adapter (scaffold only).
 *
 * Next: create / retrieve / cancel UAPI or REST flows; normalize with TravelportBookingMapper::mapBooking().
 */
final class TravelportBookingAdapter implements BookingProviderInterface, BookingTicketingProviderInterface, BookingAmendmentProviderInterface
{
    public function __construct(
        private readonly TravelportClient $client,
        private readonly TravelportBookingMapper $bookingMapper,
    ) {
    }

    public function providerCode(): string
    {
        return 'travelport';
    }

    public function createBooking(BookingCreateRequestData $request): BookingData
    {
        try {
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = (string) config('travelport.endpoints.flight_booking', '/11/air/book');
                $payload = [
                    'offer_reference' => $request->offerReference,
                    'travelers' => array_map(static fn ($traveler) => $traveler->jsonSerialize(), $request->travelers),
                    'contact_email' => $request->contactEmail,
                    'contact_phone' => $request->contactPhone,
                ];
                $raw = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => (string) Str::uuid(),
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
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('travelport.endpoints.flight_booking_retrieve', '/11/air/bookings/{id}'));
                $raw = $this->client->json()->get($endpoint, [], [
                    'X-Correlation-ID' => (string) Str::uuid(),
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
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('travelport.endpoints.flight_booking_cancel', '/11/air/bookings/{id}'));
                $raw = $this->client->json()->delete($endpoint, [], [
                    'X-Correlation-ID' => (string) Str::uuid(),
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
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('travelport.endpoints.flight_booking_ticket', '/11/air/bookings/{id}/ticket'));
                $payload = [
                    'booking_reference' => $bookingReference,
                    'context' => $opaqueContext,
                ];
                $raw = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => (string) ($opaqueContext['correlation_id'] ?? Str::uuid()),
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
            if ((bool) config('travelport.live_enabled', false)) {
                $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('travelport.endpoints.flight_booking_amend', '/11/air/bookings/{id}/change'));
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
                message: 'Travelport authentication failed.',
                supplierCode: 'TRAVELPORT_AUTH_FAILED',
                normalizedCode: 'supplier_auth_failed',
                apiError: new ApiErrorData('supplier_auth_failed', 'Travelport authentication failed.', 'TRAVELPORT_AUTH_FAILED', httpStatus: 401),
                previous: $e,
            );
        }
        if ($e instanceof ProviderValidationException) {
            return new SupplierIntegrationException(
                message: 'Travelport rejected booking request.',
                supplierCode: 'TRAVELPORT_BOOKING_INVALID',
                normalizedCode: 'supplier_request_invalid',
                supplierContext: $e->supplierContext,
                apiError: new ApiErrorData('supplier_request_invalid', 'Travelport rejected booking request.', 'TRAVELPORT_BOOKING_INVALID', httpStatus: 422),
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
