<?php

namespace App\Integrations\Duffel;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Integrations\Duffel\Mappers\DuffelFlightOfferMapper;
use App\Integrations\Duffel\Payloads\DuffelOfferRequestPayloadBuilder;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class DuffelFlightSearchAdapter implements FlightSearchProviderInterface
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly DuffelOfferRequestPayloadBuilder $payloadBuilder,
        private readonly DuffelFlightOfferMapper $offerMapper,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    public function providerCode(): string
    {
        return 'duffel';
    }

    public function searchFlights(FlightSearchRequestData $request): array
    {
        $payload = $this->payloadBuilder->forSearch($request);
        $correlationId = (string) Str::uuid();
        $tokenSelection = $this->credentialResolver->resolveDuffelTokenSelection(
            operation: 'search',
            requireIntegrationConnection: true,
        );
        $runtimeEnvironment = $tokenSelection['runtime_environment'];
        $integrationConnectionId = $tokenSelection['integration_connection_id'];
        $requestHeaders = [
            'X-Correlation-ID' => $correlationId,
        ];

        Log::info('integrations.duffel.search.credentials_resolved', [
            'provider' => $this->providerCode(),
            'operation' => 'search',
            'runtime_environment' => $runtimeEnvironment,
            'credential_source' => $tokenSelection['source'],
            'integration_connection_id' => $integrationConnectionId,
            'token_present' => trim($tokenSelection['token']) !== '',
            'token_field' => $tokenSelection['token_field'],
            'token_prefix' => $this->maskTokenPrefix($tokenSelection['token']),
            'authorization_scheme' => 'Bearer',
            'correlation_id' => $correlationId,
        ]);
        if ($runtimeEnvironment === 'sandbox' && ! str_starts_with(trim((string) $tokenSelection['token']), 'duffel_test_')) {
            Log::warning('integrations.duffel.search.token_prefix_mismatch', [
                'provider' => $this->providerCode(),
                'runtime_environment' => $runtimeEnvironment,
                'integration_connection_id' => $integrationConnectionId,
                'token_prefix' => $this->maskTokenPrefix((string) $tokenSelection['token']),
                'correlation_id' => $correlationId,
                'expected_prefix' => 'duffel_test_',
            ]);
        }

        $this->logOutboundOfferRequest($payload, $correlationId);

        $startedAt = microtime(true);
        try {
            $offerRequestResponse = $this->client->createOfferRequest(
                payload: $payload,
                returnOffers: true,
                headers: $requestHeaders,
            );
        } catch (SupplierIntegrationException $e) {
            $this->logDuffelSupplierFailure($e, $correlationId, $runtimeEnvironment, $integrationConnectionId);
            throw $e;
        }
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
            provider: $this->providerCode(),
            operation: 'search_offer_request',
            environment: $runtimeEnvironment,
            correlationId: $correlationId,
            httpMethod: 'POST',
            url: $this->buildUrl($this->client->config()->offerRequestsPath),
            requestHeaders: $requestHeaders,
            requestBody: $payload,
            integrationConnectionId: $integrationConnectionId,
            userId: auth()->id(),
            statusCode: $offerRequestResponse->statusCode,
            responseHeaders: $offerRequestResponse->headers,
            responseBody: $offerRequestResponse->decodedJson,
            latencyMs: $latencyMs,
        ));

        $offerRows = $this->extractOfferRows($offerRequestResponse->decodedJson);
        if ($offerRows === []) {
            $offerRequestId = (string) data_get($offerRequestResponse->decodedJson, 'data.id', '');
            if ($offerRequestId !== '') {
                $query = [
                    'offer_request_id' => $offerRequestId,
                    'limit' => (int) config('duffel.search.max_results', 25),
                ];
                $startedAt = microtime(true);
                $offersResponse = $this->client->listOffers($query, $requestHeaders);
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

                $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                    provider: $this->providerCode(),
                    operation: 'search_offers',
                    environment: $runtimeEnvironment,
                    correlationId: $correlationId,
                    httpMethod: 'GET',
                    url: $this->buildUrl($this->client->config()->offersPath),
                    requestHeaders: $requestHeaders,
                    requestBody: ['query' => $query],
                    integrationConnectionId: $integrationConnectionId,
                    userId: auth()->id(),
                    statusCode: $offersResponse->statusCode,
                    responseHeaders: $offersResponse->headers,
                    responseBody: $offersResponse->decodedJson,
                    latencyMs: $latencyMs,
                ));

                return $this->offerMapper->mapOffers($offersResponse->decodedJson);
            }
        }

        return $this->offerMapper->mapOffers($offerRequestResponse->decodedJson);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function extractOfferRows(array $raw): array
    {
        $rows = data_get($raw, 'data.offers', $raw['offers'] ?? $raw['data'] ?? []);
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn (mixed $row): bool => is_array($row)));
    }

    private function maskTokenPrefix(string $token): string
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return 'missing';
        }

        return substr($trimmed, 0, min(strlen($trimmed), 12)).'...';
    }

    private function buildUrl(string $endpoint): string
    {
        $base = $this->client->baseUrl() ?? $this->client->config()->baseUrl;
        if ($base === null || $base === '') {
            return $endpoint;
        }

        return rtrim($base, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logOutboundOfferRequest(array $payload, string $correlationId): void
    {
        if (! $this->shouldLogDuffelOfferRequestDebug()) {
            return;
        }

        $data = $payload['data'] ?? [];
        $passengers = is_array($data['passengers'] ?? null) ? $data['passengers'] : [];
        $passengerKinds = array_map(static function (mixed $row): string {
            if (! is_array($row)) {
                return 'invalid';
            }
            if (isset($row['type'])) {
                return 'type:'.(string) $row['type'];
            }
            if (isset($row['age'])) {
                return 'age:'.(string) $row['age'];
            }

            return 'unknown';
        }, $passengers);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Log::debug('integrations.duffel.offer_request.outbound', [
            'correlation_id' => $correlationId,
            'origin' => data_get($data, 'slices.0.origin'),
            'destination' => data_get($data, 'slices.0.destination'),
            'departure_date' => data_get($data, 'slices.0.departure_date'),
            'cabin_class' => data_get($data, 'cabin_class'),
            'passenger_kinds' => $passengerKinds,
            'request_body_json' => $json !== false ? $json : null,
        ]);
    }

    private function logDuffelSupplierFailure(
        SupplierIntegrationException $exception,
        string $correlationId,
        string $runtimeEnvironment,
        ?int $integrationConnectionId
    ): void
    {
        $status = null;
        $body = '';
        $previous = $exception->getPrevious();
        if ($previous instanceof ProviderValidationException) {
            $status = $previous->supplierContext['status_code'] ?? null;
            $body = (string) ($previous->supplierContext['body'] ?? '');
        }
        $nested = $exception->supplierContext['supplier_context'] ?? null;
        if (is_array($nested)) {
            $status = $status ?? ($nested['status_code'] ?? null);
            if ($body === '') {
                $body = (string) ($nested['body'] ?? '');
            }
        }
        if ($status === null && isset($exception->supplierContext['http_status'])) {
            $status = (int) $exception->supplierContext['http_status'];
        }

        Log::error('integrations.duffel.search.failure', [
            'correlation_id' => $correlationId,
            'provider' => $this->providerCode(),
            'operation' => 'search_offer_request',
            'error_category' => $this->classifyDuffelErrorCategory($exception),
            'normalized_code' => $exception->normalizedCode,
            'supplier_code' => $exception->supplierCode,
            'http_status' => $status,
            'runtime_environment' => $runtimeEnvironment,
            'integration_connection_id' => $integrationConnectionId,
            'response_body' => $this->truncateForSafeLog($body),
            'supplier_context' => $this->sanitizeSupplierContext($exception->supplierContext),
        ]);
    }

    private function shouldLogDuffelOfferRequestDebug(): bool
    {
        return (bool) config('app.debug', false) || (bool) config('integrations.debug_duffel_auth', false);
    }

    private function truncateForSafeLog(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $max = 12000;

        return strlen($trimmed) <= $max ? $trimmed : substr($trimmed, 0, $max).'…(truncated)';
    }

    private function classifyDuffelErrorCategory(SupplierIntegrationException $exception): string
    {
        return match ($exception->normalizedCode) {
            'supplier_auth_failed' => 'auth',
            'supplier_request_invalid' => 'payload',
            'supplier_transport_failed', 'supplier_rate_limited' => 'network',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeSupplierContext(array $context): array
    {
        $sanitized = $context;
        foreach (['authorization', 'access_token', 'token', 'api_token', 'api_key', 'client_secret'] as $sensitiveKey) {
            if (array_key_exists($sensitiveKey, $sanitized)) {
                $sanitized[$sensitiveKey] = '[redacted]';
            }
        }

        return $sanitized;
    }
}
