<?php

namespace App\Integrations\Amadeus;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\BookingAmendmentProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\BookingTicketingProviderInterface;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\Mappers\AmadeusBookingMapper;
use App\Integrations\Amadeus\Support\AmadeusOfferReferenceCodec;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;

/**
 * Phase 11.8 — Amadeus booking adapter (scaffold only).
 *
 * Next: Order Management / booking APIs; AmadeusBookingMapper::mapBooking().
 */
final class AmadeusBookingAdapter implements BookingProviderInterface, BookingTicketingProviderInterface, BookingAmendmentProviderInterface
{
    public function __construct(
        private readonly AmadeusClient $client,
        private readonly AmadeusBookingMapper $bookingMapper,
        private readonly AmadeusOfferReferenceCodec $codec,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
    }

    public function createBooking(BookingCreateRequestData $request): BookingData
    {
        if ((bool) config('amadeus.live_enabled', false)) {
            $payload = $this->bookingPayload($request);
            if ($payload !== null) {
                $endpoint = (string) config('amadeus.endpoints.flight_booking', '/v1/booking/flight-orders');
                $correlationId = (string) Str::uuid();
                $startedAt = microtime(true);
                $response = $this->client->json()->post($endpoint, $payload, [
                    'X-Correlation-ID' => $correlationId,
                ]);
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                    provider: $this->providerCode(),
                    operation: 'booking',
                    environment: $this->runtimeEnvironment(),
                    correlationId: $correlationId,
                    httpMethod: 'POST',
                    url: $this->buildUrl($endpoint),
                    requestBody: $payload,
                    integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                    userId: auth()->id(),
                    statusCode: $response->statusCode,
                    responseHeaders: $response->headers,
                    responseBody: $response->decodedJson,
                    latencyMs: $latencyMs,
                ));

                return $this->bookingMapper->mapBooking($response->decodedJson);
            }
        }

        return new BookingData(
            status: 'not_implemented',
            providerCode: $this->providerCode(),
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function retrieveBooking(string $bookingReference): BookingData
    {
        if ((bool) config('amadeus.live_enabled', false)) {
            $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('amadeus.endpoints.flight_booking_retrieve', '/v1/booking/flight-orders/{id}'));
            $correlationId = (string) Str::uuid();
            $startedAt = microtime(true);
            $response = $this->client->json()->get($endpoint, [], [
                'X-Correlation-ID' => $correlationId,
            ]);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'booking_retrieve',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'GET',
                url: $this->buildUrl($endpoint),
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));

            return $this->bookingMapper->mapBooking($response->decodedJson);
        }

        return new BookingData(
            status: 'not_found',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        if ((bool) config('amadeus.live_enabled', false)) {
            $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('amadeus.endpoints.flight_booking_cancel', '/v1/booking/flight-orders/{id}'));
            $correlationId = (string) Str::uuid();
            $startedAt = microtime(true);
            $response = $this->client->json()->delete($endpoint, [], [
                'X-Correlation-ID' => $correlationId,
            ]);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'booking_cancel',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'DELETE',
                url: $this->buildUrl($endpoint),
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));

            return new BookingData(
                status: 'cancelled',
                providerCode: $this->providerCode(),
                bookingReference: $bookingReference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        }

        return new BookingData(
            status: 'not_implemented',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function ticketBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        if ((bool) config('amadeus.live_enabled', false)) {
            $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('amadeus.endpoints.flight_booking_ticket', '/v1/booking/flight-orders/{id}/ticket'));
            $payload = [
                'data' => [
                    'type' => 'flight-order-ticket',
                    'issueInstantTicket' => true,
                ],
            ];
            $correlationId = (string) ($opaqueContext['correlation_id'] ?? Str::uuid()->toString());
            $startedAt = microtime(true);
            $response = $this->client->json()->post($endpoint, $payload, [
                'X-Correlation-ID' => $correlationId,
            ]);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'booking_ticket',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'POST',
                url: $this->buildUrl($endpoint),
                requestBody: $payload,
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));

            $mapped = $this->bookingMapper->mapBooking($response->decodedJson);

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
    }

    public function amendBooking(string $bookingReference, array $amendmentPayload = []): BookingData
    {
        if ((bool) config('amadeus.live_enabled', false)) {
            $endpoint = str_replace('{id}', rawurlencode($bookingReference), (string) config('amadeus.endpoints.flight_booking_amend', '/v1/booking/flight-orders/{id}/change'));
            $payload = [
                'data' => [
                    'type' => 'flight-order-change',
                    'changes' => $amendmentPayload,
                ],
            ];
            $correlationId = (string) Str::uuid();
            $startedAt = microtime(true);
            $response = $this->client->json()->post($endpoint, $payload, [
                'X-Correlation-ID' => $correlationId,
            ]);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'booking_amend',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'POST',
                url: $this->buildUrl($endpoint),
                requestBody: $payload,
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));

            return $this->bookingMapper->mapBooking($response->decodedJson);
        }

        return new BookingData(
            status: 'amended',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bookingPayload(BookingCreateRequestData $request): ?array
    {
        $offer = $this->codec->decode($request->offerReference);
        if ($offer === null) {
            return null;
        }

        $travelers = [];
        foreach ($request->travelers as $index => $traveler) {
            $travelers[] = [
                'id' => (string) ($index + 1),
                'dateOfBirth' => $traveler->dateOfBirth ?? null,
                'name' => [
                    'firstName' => $traveler->givenName,
                    'lastName' => $traveler->familyName,
                ],
                'travelerType' => strtoupper($traveler->travelerType),
                'contact' => [
                    'emailAddress' => $request->contactEmail,
                    'phones' => $request->contactPhone ? [['deviceType' => 'MOBILE', 'number' => $request->contactPhone]] : [],
                ],
            ];
        }

        return [
            'data' => [
                'type' => 'flight-order',
                'flightOffers' => [$offer],
                'travelers' => $travelers,
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
