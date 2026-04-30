<?php

namespace App\Integrations\Duffel;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\SupplierHttpResponse;
use App\Integrations\Shared\BaseApiClient;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderMappingException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DuffelClient extends BaseApiClient
{
    public function __construct(
        AuthTokenProviderInterface $auth,
        private readonly SupplierJsonHttpClientInterface $jsonHttp,
        ?DuffelConfig $config = null,
    ) {
        parent::__construct($auth);
        $this->config = $config ?? DuffelConfig::fromConfig($jsonHttp->baseUrl());
    }

    private readonly DuffelConfig $config;

    public function providerCode(): string
    {
        return 'duffel';
    }

    public function json(): SupplierJsonHttpClientInterface
    {
        return $this->jsonHttp;
    }

    public function baseUrl(): ?string
    {
        return $this->jsonHttp->baseUrl();
    }

    public function config(): DuffelConfig
    {
        return $this->config;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function createOfferRequest(array $payload, bool $returnOffers = true, array $headers = []): SupplierHttpResponse
    {
        $path = $this->config->offerRequestsPath;
        if ($returnOffers) {
            $separator = str_contains($path, '?') ? '&' : '?';
            $path .= $separator.'return_offers=true';
        }

        return $this->post($path, $payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function getOffer(string $offerId, array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->get(rtrim($this->config->offersPath, '/').'/'.ltrim($offerId, '/'), $query, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function getOfferRequest(string $offerRequestId, array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->get(rtrim($this->config->offerRequestsPath, '/').'/'.ltrim($offerRequestId, '/'), $query, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function listOffers(array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->get($this->config->offersPath, $query, $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function createOrder(array $payload, array $headers = []): SupplierHttpResponse
    {
        return $this->post($this->config->ordersPath, $payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function getOrder(string $orderId, array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->get(rtrim($this->config->ordersPath, '/').'/'.ltrim($orderId, '/'), $query, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(string $uri, array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->execute(
            fn (): SupplierHttpResponse => $this->jsonHttp->get($uri, $query, $this->headers($headers)),
            method: 'GET',
            uri: $uri,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function post(string $uri, array $payload = [], array $headers = []): SupplierHttpResponse
    {
        return $this->execute(
            fn (): SupplierHttpResponse => $this->jsonHttp->post($uri, $payload, $this->headers($headers)),
            method: 'POST',
            uri: $uri,
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers = []): array
    {
        return $this->config->headers($headers);
    }

    /**
     * @param  callable(): SupplierHttpResponse  $callback
     */
    private function execute(callable $callback, string $method, string $uri): SupplierHttpResponse
    {
        try {
            return $callback();
        } catch (ProviderAuthException|ProviderRateLimitException|ProviderTransportException|ProviderValidationException|ProviderMappingException $exception) {
            throw $this->normalizeException($exception, $method, $uri);
        } catch (Throwable $exception) {
            throw $this->normalizeUnknownException($exception, $method, $uri);
        }
    }

    private function normalizeException(
        ProviderAuthException|ProviderRateLimitException|ProviderTransportException|ProviderValidationException|ProviderMappingException $exception,
        string $method,
        string $uri
    ): SupplierIntegrationException {
        $normalized = match (true) {
            $exception instanceof ProviderAuthException => ['supplier_auth_failed', 401, 'DUFFEL_AUTH_FAILED'],
            $exception instanceof ProviderRateLimitException => ['supplier_rate_limited', 429, 'DUFFEL_RATE_LIMITED'],
            $exception instanceof ProviderValidationException => ['supplier_request_invalid', 422, 'DUFFEL_VALIDATION_FAILED'],
            $exception instanceof ProviderTransportException => ['supplier_transport_failed', 503, 'DUFFEL_TRANSPORT_FAILED'],
            default => ['supplier_mapping_failed', 502, 'DUFFEL_MAPPING_FAILED'],
        };

        $message = trim($exception->getMessage());
        if ($message === '') {
            $message = 'Duffel request failed for an unknown reason.';
        }

        $context = [
            'provider' => $this->providerCode(),
            'method' => $method,
            'uri' => $uri,
        ];
        if ($exception instanceof ProviderValidationException) {
            $context['supplier_context'] = $exception->supplierContext;
            if ($this->shouldLogValidationDebug()) {
                Log::warning('integrations.duffel.validation_failed', [
                    'provider' => $this->providerCode(),
                    'method' => $method,
                    'uri' => $uri,
                    'http_status' => 422,
                    'response_body_preview' => $this->truncateBodyPreview((string) ($exception->supplierContext['body'] ?? '')),
                    'authorization_scheme' => 'Bearer',
                ]);
            }
        }
        if ($exception instanceof ProviderRateLimitException && $exception->retryAfterSeconds !== null) {
            $context['retry_after_seconds'] = $exception->retryAfterSeconds;
        }
        if ($exception instanceof ProviderAuthException) {
            $context['http_status'] = $exception->httpStatus;
        }

        return new SupplierIntegrationException(
            message: mb_substr($message, 0, 2000),
            supplierCode: $normalized[2],
            normalizedCode: $normalized[0],
            supplierContext: $context,
            previous: $exception,
            apiError: new ApiErrorData(
                code: $normalized[0],
                message: mb_substr($message, 0, 2000),
                supplierCode: $normalized[2],
                httpStatus: $normalized[1],
            ),
        );
    }

    private function normalizeUnknownException(Throwable $exception, string $method, string $uri): SupplierIntegrationException
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            $message = 'Unexpected Duffel client error.';
        }

        return new SupplierIntegrationException(
            message: mb_substr($message, 0, 2000),
            supplierCode: 'DUFFEL_CLIENT_ERROR',
            normalizedCode: 'supplier_client_error',
            supplierContext: [
                'provider' => $this->providerCode(),
                'method' => $method,
                'uri' => $uri,
            ],
            previous: $exception,
            apiError: new ApiErrorData(
                code: 'supplier_client_error',
                message: mb_substr($message, 0, 2000),
                supplierCode: 'DUFFEL_CLIENT_ERROR',
                httpStatus: 500,
            ),
        );
    }

    private function shouldLogValidationDebug(): bool
    {
        return (bool) config('app.debug', false) || (bool) config('integrations.debug_duffel_auth', false);
    }

    private function truncateBodyPreview(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return 'empty';
        }

        return mb_substr($trimmed, 0, 500);
    }
}
