<?php

namespace App\Integrations\Duffel;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Str;
use Throwable;

final class DuffelCancellationAdapter
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly RecordIntegrationRawExchangeAction $recordRawExchange,
    ) {
    }

    /**
     * Prepare cancellation context while keeping destructive actions opt-in.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function prepareCancellation(string $orderReference, array $context = []): array
    {
        try {
            $correlationId = trim((string) ($context['correlation_id'] ?? ''));
            if ($correlationId === '') {
                $correlationId = (string) Str::uuid();
            }
            $headers = ['X-Correlation-ID' => $correlationId];
            $order = $this->retrieveOrder($orderReference, $headers, $correlationId);
            $requiresApproval = true;
            $remoteCancellationEnabled = (bool) config('duffel.cancellation_live_enabled', false);

            $result = [
                'status' => 'prepared',
                'provider' => $this->providerCode(),
                'booking_reference' => $orderReference,
                'correlation_id' => $correlationId,
                'supplier_status' => $this->normalizeStatus((string) ($order['status'] ?? '')),
                'requires_approval' => $requiresApproval,
                'remote_cancellation_enabled' => $remoteCancellationEnabled,
                'reason' => isset($context['reason']) ? (string) $context['reason'] : null,
                'cancelled_at' => null,
            ];

            if ($remoteCancellationEnabled && (bool) ($context['execute_remote'] ?? false)) {
                $cancelled = $this->executeRemoteCancellation($orderReference, $headers, $correlationId);
                $result['status'] = $this->normalizeStatus((string) ($cancelled['status'] ?? 'cancelled'));
                $result['cancelled_at'] = $cancelled['cancelled_at'] ?? now()->toIso8601String();
            }

            return $result;
        } catch (Throwable $e) {
            throw $this->normalizeError($e);
        }
    }

    /**
     * Structural placeholder for future Duffel order-change support.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function prepareOrderChangeGroundwork(string $orderReference, array $context = []): array
    {
        return [
            'provider' => $this->providerCode(),
            'booking_reference' => $orderReference,
            'change_support_status' => 'prepared_not_implemented',
            'supported_actions' => ['change', 'repricing'],
            'approval_required' => true,
            'correlation_id' => (string) ($context['correlation_id'] ?? Str::uuid()),
        ];
    }

    public function providerCode(): string
    {
        return 'duffel';
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function retrieveOrder(string $orderReference, array $headers, string $correlationId): array
    {
        $startedAt = microtime(true);
        $response = $this->client->getOrder($orderReference, [], $headers);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
            provider: $this->providerCode(),
            operation: 'booking_cancel_prepare',
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
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function executeRemoteCancellation(string $orderReference, array $headers, string $correlationId): array
    {
        $path = rtrim($this->client->config()->ordersPath, '/').'/'.rawurlencode($orderReference).'/actions/cancel';
        $payload = ['data' => ['type' => 'order_cancellation']];
        $startedAt = microtime(true);
        $response = $this->client->post($path, $payload, $headers);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->recordRawExchange->execute(new IntegrationRawExchangeData(
            provider: $this->providerCode(),
            operation: 'booking_cancel_execute',
            environment: $this->runtimeEnvironment(),
            correlationId: $correlationId,
            httpMethod: 'POST',
            url: $this->buildUrl($path),
            requestHeaders: $headers,
            requestBody: $payload,
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

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return 'pending';
        }

        return match ($normalized) {
            'cancelled', 'canceled', 'voided' => 'cancelled',
            'confirmed', 'ticketed' => 'confirmed',
            'failed', 'rejected' => 'failed',
            default => $normalized,
        };
    }

    private function normalizeError(Throwable $e): SupplierIntegrationException
    {
        if ($e instanceof SupplierIntegrationException) {
            return $e;
        }

        $message = trim($e->getMessage());
        if ($message === '') {
            $message = 'Duffel cancellation preparation failed.';
        }

        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'DUFFEL_CANCELLATION_FAILED',
            normalizedCode: 'supplier_cancellation_failed',
            apiError: new ApiErrorData(
                code: 'supplier_cancellation_failed',
                message: $message,
                supplierCode: 'DUFFEL_CANCELLATION_FAILED',
                httpStatus: 502,
            ),
            previous: $e,
        );
    }
}
