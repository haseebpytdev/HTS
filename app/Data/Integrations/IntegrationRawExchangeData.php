<?php

namespace App\Data\Integrations;

/**
 * Raw HTTP exchange to persist for audit / replay analysis (paired request + response rows).
 */
final readonly class IntegrationRawExchangeData
{
    /**
     * @param  array<string, mixed>|null  $requestHeaders
     * @param  array<string, mixed>|null  $requestBody
     * @param  array<string, mixed>|null  $responseHeaders
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        public string $provider,
        public string $operation,
        public string $environment,
        public string $correlationId,
        public ?string $traceId = null,
        public ?string $httpMethod = null,
        public ?string $url = null,
        public ?array $requestHeaders = null,
        public ?array $requestBody = null,
        public ?int $integrationConnectionId = null,
        public ?int $userId = null,
        public ?int $statusCode = null,
        public ?array $responseHeaders = null,
        public ?array $responseBody = null,
        public ?int $latencyMs = null,
        public ?string $errorCategory = null,
    ) {
    }
}
