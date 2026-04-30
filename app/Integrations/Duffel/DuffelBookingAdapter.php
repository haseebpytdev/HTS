<?php

namespace App\Integrations\Duffel;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Integrations\Duffel\Mappers\DuffelBookingMapper;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;
use Throwable;

final class DuffelBookingAdapter implements BookingProviderInterface
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly DuffelBookingMapper $bookingMapper,
        private readonly DuffelCancellationAdapter $cancellationAdapter,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return 'duffel';
    }

    public function createBooking(BookingCreateRequestData $request): BookingData
    {
        if (! (bool) config('duffel.booking_live_enabled', false)) {
            return new BookingData(
                status: 'not_implemented',
                providerCode: $this->providerCode(),
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
            );
        }

        try {
            $correlationId = (string) Str::uuid();
            $payload = $this->buildOrderPayload($request);
            $requestHeaders = ['X-Correlation-ID' => $correlationId];
            $startedAt = microtime(true);
            $response = $this->client->createOrder($payload, $requestHeaders);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'booking_create',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'POST',
                url: $this->buildUrl($this->client->config()->ordersPath),
                requestHeaders: $requestHeaders,
                requestBody: $payload,
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));

            return $this->bookingMapper->mapBooking($response->decodedJson);
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderPayload(BookingCreateRequestData $request): array
    {
        $passengers = [];
        foreach ($request->travelers as $index => $traveler) {
            $passenger = array_filter([
                'id' => 'pas_'.($index + 1),
                'type' => $this->normalizeTravelerType($traveler->travelerType),
                'given_name' => $traveler->givenName,
                'family_name' => $traveler->familyName,
                'born_on' => $traveler->dateOfBirth,
                'email' => $request->contactEmail,
                'phone_number' => $request->contactPhone,
            ], static fn (mixed $value): bool => ! ($value === null || $value === ''));

            $passengers[] = $passenger;
        }

        return [
            'data' => [
                'type' => 'order',
                'selected_offers' => [$request->offerReference],
                'passengers' => $passengers,
            ],
        ];
    }

    private function normalizeTravelerType(string $travelerType): string
    {
        $normalized = strtolower(trim($travelerType));

        return match ($normalized) {
            'adult', 'adt' => 'adult',
            'child', 'chd' => 'child',
            'infant', 'inf' => 'infant_without_seat',
            default => 'adult',
        };
    }

    public function retrieveBooking(string $bookingReference): BookingData
    {
        return new BookingData(
            status: 'not_found',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        if ((bool) config('duffel.cancellation_live_enabled', false)) {
            $prepared = $this->cancellationAdapter->prepareCancellation($bookingReference, $opaqueContext);

            return new BookingData(
                status: (string) ($prepared['status'] ?? 'cancelled'),
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

    private function normalizeError(Throwable $e): SupplierIntegrationException
    {
        if ($e instanceof SupplierIntegrationException) {
            return $e;
        }

        return new SupplierIntegrationException(
            message: $e->getMessage(),
            supplierCode: 'DUFFEL_BOOKING_FAILED',
            normalizedCode: 'supplier_booking_failed',
            apiError: new ApiErrorData(
                code: 'supplier_booking_failed',
                message: $e->getMessage(),
                supplierCode: 'DUFFEL_BOOKING_FAILED',
                httpStatus: 502,
            ),
            previous: $e,
        );
    }
}
