<?php

namespace App\Integrations\Duffel;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;
use Throwable;

final class DuffelPaymentAdapter
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function settleHoldOrderIfRequired(string $orderReference, array $context = []): array
    {
        try {
            $correlationId = trim((string) ($context['correlation_id'] ?? ''));
            if ($correlationId === '') {
                $correlationId = (string) Str::uuid();
            }
            $headers = ['X-Correlation-ID' => $correlationId];

            $repricedOrder = $this->retrieveLatestOrder($orderReference, $headers, $correlationId);
            if (! $this->requiresPostHoldPayment($repricedOrder)) {
                return [
                    'requires_payment' => false,
                    'status' => 'skipped',
                    'booking_reference' => $orderReference,
                    'correlation_id' => $correlationId,
                    'repriced_total_amount' => $this->money($repricedOrder['total_amount'] ?? null),
                    'repriced_currency' => strtoupper((string) ($repricedOrder['total_currency'] ?? $repricedOrder['currency'] ?? '')),
                ];
            }

            $currency = strtoupper((string) ($repricedOrder['total_currency'] ?? $repricedOrder['currency'] ?? 'USD'));
            $amount = $this->money($repricedOrder['total_amount'] ?? $context['total_amount'] ?? 0);
            if ($amount <= 0) {
                throw new SupplierIntegrationException(
                    message: 'Duffel hold-order payment amount is invalid after repricing.',
                    supplierCode: 'DUFFEL_PAYMENT_INVALID_AMOUNT',
                    normalizedCode: 'supplier_request_invalid',
                    apiError: new ApiErrorData(
                        code: 'supplier_request_invalid',
                        message: 'Duffel hold-order payment amount is invalid after repricing.',
                        supplierCode: 'DUFFEL_PAYMENT_INVALID_AMOUNT',
                        httpStatus: 422,
                    ),
                );
            }

            $payload = [
                'data' => [
                    'type' => 'payment',
                    'order_id' => $orderReference,
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                ],
            ];
            $startedAt = microtime(true);
            $response = $this->client->post($this->paymentsPath(), $payload, $headers);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordRawExchange->execute(new IntegrationRawExchangeData(
                provider: $this->providerCode(),
                operation: 'booking_payment',
                environment: $this->runtimeEnvironment(),
                correlationId: $correlationId,
                httpMethod: 'POST',
                url: $this->buildUrl($this->paymentsPath()),
                requestHeaders: $headers,
                requestBody: $payload,
                integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
                userId: auth()->id(),
                statusCode: $response->statusCode,
                responseHeaders: $response->headers,
                responseBody: $response->decodedJson,
                latencyMs: $latencyMs,
            ));

            $paymentData = is_array($response->decodedJson['data'] ?? null)
                ? $response->decodedJson['data']
                : $response->decodedJson;
            $paymentReference = trim((string) ($paymentData['id'] ?? ''));
            $paymentStatus = strtolower(trim((string) ($paymentData['status'] ?? 'succeeded')));

            return [
                'requires_payment' => true,
                'status' => $paymentStatus === '' ? 'succeeded' : $paymentStatus,
                'booking_reference' => $orderReference,
                'correlation_id' => $correlationId,
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'repriced_total_amount' => $amount,
                'repriced_currency' => $currency,
            ];
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    public function providerCode(): string
    {
        return 'duffel';
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function retrieveLatestOrder(string $orderReference, array $headers, string $correlationId): array
    {
        $startedAt = microtime(true);
        $response = $this->client->getOrder($orderReference, [], $headers);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
            provider: $this->providerCode(),
            operation: 'booking_payment_reprice',
            environment: $this->runtimeEnvironment(),
            correlationId: $correlationId,
            httpMethod: 'GET',
            url: $this->buildUrl($this->client->config()->ordersPath.'/'.$orderReference),
            requestHeaders: $headers,
            integrationConnectionId: $this->credentialResolver->forProvider($this->providerCode(), operation: 'booking')->integrationConnectionId,
            userId: auth()->id(),
            statusCode: $response->statusCode,
            responseHeaders: $response->headers,
            responseBody: $response->decodedJson,
            latencyMs: $latencyMs,
        ));

        $data = is_array($response->decodedJson['data'] ?? null)
            ? $response->decodedJson['data']
            : $response->decodedJson;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function requiresPostHoldPayment(array $order): bool
    {
        $paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? '')));
        if (in_array($paymentStatus, ['awaiting_payment', 'payment_required', 'requires_payment'], true)) {
            return true;
        }

        $status = strtolower(trim((string) ($order['status'] ?? '')));

        return in_array($status, ['awaiting_payment', 'pending', 'on_hold'], true);
    }

    private function paymentsPath(): string
    {
        $path = trim((string) config('duffel.payments_path', '/air/payments'));

        return $path !== '' ? $path : '/air/payments';
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

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return (float) $value;
    }

    private function normalizeError(Throwable $e): SupplierIntegrationException
    {
        if ($e instanceof SupplierIntegrationException) {
            return $e;
        }

        $message = trim($e->getMessage());
        if ($message === '') {
            $message = 'Duffel hold-order payment failed.';
        }

        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'DUFFEL_PAYMENT_FAILED',
            normalizedCode: 'supplier_payment_failed',
            apiError: new ApiErrorData(
                code: 'supplier_payment_failed',
                message: $message,
                supplierCode: 'DUFFEL_PAYMENT_FAILED',
                httpStatus: 502,
            ),
            previous: $e,
        );
    }
}
