<?php

namespace App\Integrations\Duffel;

final readonly class DuffelConfig
{
    /**
     * @param  array<string, string>  $defaultHeaders
     */
    public function __construct(
        public ?string $baseUrl,
        public int $timeoutSeconds,
        public string $offerRequestsPath,
        public string $offersPath,
        public string $ordersPath,
        public array $defaultHeaders,
    ) {
    }

    public static function fromConfig(?string $baseUrlOverride = null): self
    {
        $environment = self::environmentKey();
        $envCfg = (array) config("duffel.environments.{$environment}", []);
        $baseUrl = trim((string) ($baseUrlOverride ?? $envCfg['base_url'] ?? config('duffel.base_url', '')));
        $version = trim((string) config('duffel.version', 'v2'));
        $timeoutSeconds = max(
            1,
            (int) config('duffel.timeout_seconds', config('integrations.http_timeout_seconds', 30))
        );

        return new self(
            baseUrl: $baseUrl !== '' ? $baseUrl : null,
            timeoutSeconds: $timeoutSeconds,
            offerRequestsPath: (string) config('duffel.offer_requests_path', '/air/offer_requests'),
            offersPath: (string) config('duffel.offers_path', '/air/offers'),
            ordersPath: (string) config('duffel.orders_path', '/air/orders'),
            defaultHeaders: array_filter([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Duffel-Version' => $version !== '' ? $version : null,
            ], static fn (?string $value): bool => is_string($value) && $value !== ''),
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public function headers(array $headers = []): array
    {
        return array_merge($this->defaultHeaders, $headers);
    }

    private static function environmentKey(): string
    {
        $raw = strtolower((string) config('integrations.credential_environment', 'production'));

        return in_array($raw, ['test', 'sandbox', 'testing', 'development'], true)
            ? 'test'
            : 'production';
    }
}

