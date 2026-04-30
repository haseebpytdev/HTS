<?php

namespace App\Services\Integrations;

use App\Integrations\AmadeusSelfService\AmadeusSelfServiceConnectionTester;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\AmadeusAuthService;
use App\Integrations\Duffel\DuffelClient;
use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Integrations\Shared\SupplierJsonHttpClient;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Sabre\SabreAuthService;
use App\Integrations\Travelport\TravelportAuthService;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider as AmadeusSelfServiceProviderAlias;
use App\Models\IntegrationConnection;
use App\Repositories\IntegrationTokenRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ConnectionHealthCheckService
{
    public function __construct(
        private readonly ProviderCredentialResolver $credentialResolver,
        private readonly TravelportAuthService $travelportAuth,
        private readonly SabreAuthService $sabreAuth,
        private readonly AmadeusAuthService $amadeusAuth,
        private readonly IntegrationTokenRepository $tokenRepository,
        private readonly AmadeusSelfServiceConnectionTester $amadeusSelfServiceTester,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, token_expires_at: ?string}
     */
    public function check(IntegrationConnection $connection): array
    {
        if (strtolower((string) $connection->provider) === 'duffel') {
            return $this->checkDuffelConnection($connection);
        }
        if (strtolower((string) $connection->provider) === 'sabre') {
            return $this->checkSabreConnection($connection);
        }

        if (AmadeusSelfServiceProvider::matches((string) $connection->provider)) {
            return $this->amadeusSelfServiceTester->test(
                connection: $connection,
                credentials: $this->credentialMap($connection, allowResolverFallback: false),
                stubMode: $this->isStubMode(),
            );
        }

        $credentials = $this->credentialMap($connection);
        if ($this->isStubMode()) {
            return [
                'ok' => true,
                'message' => 'Stub test mode: simulated healthy connection.',
                'token_expires_at' => now()->addHour()->toDateTimeString(),
            ];
        }

        if (blank($connection->base_url) || blank($credentials['client_id'] ?? null) || blank($credentials['client_secret'] ?? null)) {
            return [
                'ok' => false,
                'message' => 'Connection config incomplete: base URL / client_id / client_secret required.',
                'token_expires_at' => null,
            ];
        }

        try {
            $this->authServiceForProvider($connection->provider)->getAccessToken();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->normalizeError($e, (string) $connection->provider),
                'token_expires_at' => null,
            ];
        }

        $stored = $this->tokenRepository->findLatestValidAccessToken($connection);

        return [
            'ok' => true,
            'message' => 'Connection test passed.',
            'token_expires_at' => $stored?->expiresAt->toDateTimeString(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, token_expires_at: ?string}
     */
    private function checkDuffelConnection(IntegrationConnection $connection): array
    {
        $tokenSelection = $this->credentialResolver->resolveDuffelTokenForConnection($connection);
        $apiToken = trim($tokenSelection['token']);
        if ($apiToken === '') {
            return [
                'ok' => false,
                'message' => 'Duffel api_token is required to test this connection.',
                'token_expires_at' => null,
            ];
        }

        $environment = $tokenSelection['runtime_environment'];
        $isSandboxEnvironment = $environment === 'sandbox';
        if ($isSandboxEnvironment && ! str_starts_with($apiToken, 'duffel_test_')) {
            return [
                'ok' => false,
                'message' => 'Duffel test environment requires an api_token that starts with duffel_test_.',
                'token_expires_at' => null,
            ];
        }

        $runtimeSelection = null;
        if (in_array((string) $connection->status, ['healthy', 'connected'], true)) {
            $runtimeSelection = $this->credentialResolver->resolveDuffelTokenSelection(
                tenantId: $connection->tenant_id !== null ? (int) $connection->tenant_id : null,
                operation: 'search',
                runtimeEnvironmentOverride: $environment,
            );
        }
        if (($runtimeSelection['integration_connection_id'] ?? (int) $connection->id) !== (int) $connection->id) {
            return [
                'ok' => false,
                'message' => 'Duffel connection test is not using the same credential record selected by the real search path for this environment.',
                'token_expires_at' => null,
            ];
        }

        $baseUrl = trim((string) ($connection->base_url ?: config('duffel.base_url', '')));
        if ($baseUrl === '') {
            return [
                'ok' => false,
                'message' => 'Duffel base URL is required to test this connection.',
                'token_expires_at' => null,
            ];
        }

        $version = (string) config('duffel.version', 'v2');
        $healthPath = (string) config('duffel.health_check_path', '/air/airlines?limit=1');
        $correlationId = (string) Str::uuid();
        $url = rtrim($baseUrl, '/').'/'.ltrim($healthPath, '/');

        Log::info('integrations.duffel.connection_test.credentials_resolved', [
            'provider' => 'duffel',
            'operation' => 'search_connection_test',
            'runtime_environment' => $environment,
            'credential_source' => $tokenSelection['source'],
            'integration_connection_id' => $tokenSelection['integration_connection_id'],
            'token_present' => $apiToken !== '',
            'token_field' => $tokenSelection['token_field'],
            'token_prefix' => $this->maskTokenPrefix($apiToken),
            'authorization_scheme' => 'Bearer',
            'selected_connection_id' => $runtimeSelection['integration_connection_id'] ?? (int) $connection->id,
            'correlation_id' => $correlationId,
        ]);

        try {
            $response = $this->executeWithDuffelClientToken(
                token: $apiToken,
                baseUrl: $baseUrl,
                callback: static fn (DuffelClient $client) => $client->get($healthPath, headers: [
                    'X-Correlation-ID' => $correlationId,
                    'Duffel-Version' => $version,
                ]),
            );
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->normalizeError($e, 'duffel'),
                'token_expires_at' => null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Duffel token is valid and accepted by API.',
            'token_expires_at' => null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, token_expires_at: ?string}
     */
    private function checkSabreConnection(IntegrationConnection $connection): array
    {
        if ($this->isStubMode()) {
            return [
                'ok' => true,
                'message' => 'Stub test mode: simulated healthy connection.',
                'token_expires_at' => now()->addHour()->toDateTimeString(),
            ];
        }

        $token = null;
        try {
            $token = $this->sabreAuth->getAccessToken();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->normalizeError($e, 'sabre'),
                'token_expires_at' => null,
            ];
        }

        $stored = $this->tokenRepository->findLatestValidAccessToken($connection);
        $tokenExpiresAt = $stored?->expiresAt->toDateTimeString();

        if (! $this->shouldRunSabreDeepTest($connection)) {
            return [
                'ok' => true,
                'message' => 'Connection test passed (token-only).',
                'token_expires_at' => $tokenExpiresAt,
            ];
        }

        $deep = $this->runSabreDeepSearchProbe($connection, $token);
        if (! $deep['ok']) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'Sabre deep test failed [%s]: %s',
                    $deep['category'],
                    $deep['message']
                ),
                'token_expires_at' => $tokenExpiresAt,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Connection test passed (token + deep BFM probe).',
            'token_expires_at' => $tokenExpiresAt,
        ];
    }

    private function shouldRunSabreDeepTest(IntegrationConnection $connection): bool
    {
        $connectionConfig = is_array($connection->config) ? $connection->config : [];

        return (bool) ($connectionConfig['sabre_deep_test'] ?? false)
            || (bool) config('integrations.health_checks.sabre_deep_test_enabled', false);
    }

    /**
     * @return array{ok: bool, category: string, message: string}
     */
    private function runSabreDeepSearchProbe(IntegrationConnection $connection, string $token): array
    {
        $baseUrl = trim((string) $connection->base_url);
        $endpoint = (string) config('sabre.endpoints.flight_search', '/v5/offers/shop');
        $url = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
        $futureDate = now()->addDays(30)->toDateString();
        $correlationId = (string) Str::uuid();

        $payload = [
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '5',
                'OriginDestinationInformation' => [[
                    'RPH' => '1',
                    'DepartureDateTime' => $futureDate.'T00:00:00',
                    'OriginLocation' => ['LocationCode' => 'LHR'],
                    'DestinationLocation' => ['LocationCode' => 'JFK'],
                ]],
                'TravelerInfoSummary' => [
                    'SeatsRequested' => [1],
                    'AirTravelerAvail' => [[
                        'PassengerTypeQuantity' => [
                            ['Code' => 'ADT', 'Quantity' => 1],
                        ],
                    ]],
                ],
                'TPA_Extensions' => [
                    'IntelliSellTransaction' => [
                        'RequestType' => [
                            'Name' => (string) config('sabre.bfm.request_type', '50ITINS'),
                        ],
                    ],
                ],
                'TravelPreferences' => [
                    'CabinPref' => [[
                        'Cabin' => 'ECONOMY',
                        'PreferLevel' => 'Preferred',
                    ]],
                ],
            ],
        ];

        try {
            $response = Http::timeout((int) config('integrations.http_timeout_seconds', 30))
                ->asJson()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                    'X-Correlation-ID' => $correlationId,
                ])
                ->post($url, $payload);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'category' => 'endpoint',
                'message' => 'Endpoint transport error: '.mb_substr($e->getMessage(), 0, 500),
            ];
        }

        $status = $response->status();
        if ($status >= 200 && $status < 300) {
            return [
                'ok' => true,
                'category' => 'none',
                'message' => 'Deep BFM probe accepted by endpoint.',
            ];
        }

        $category = match (true) {
            $status === 401 => 'auth',
            $status === 403 => 'entitlement',
            in_array($status, [400, 422], true) => 'payload',
            default => 'endpoint',
        };

        return [
            'ok' => false,
            'category' => $category,
            'message' => sprintf('HTTP %d from %s', $status, $url),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function credentialMap(IntegrationConnection $connection, bool $allowResolverFallback = true): array
    {
        $connection->loadMissing('credentials');

        $environment = strtolower((string) ($connection->environment ?: 'sandbox'));
        $environmentPrefixes = $environment === 'production' ? ['production'] : ['sandbox', 'test', 'testing'];
        $out = [];
        foreach ($connection->credentials as $credential) {
            $key = strtolower((string) ($credential->credential_key ?? ''));
            if ($key === '') {
                continue;
            }

            $value = (string) $credential->credential_value_encrypted;
            $out[$key] = $value;

            foreach ($environmentPrefixes as $prefix) {
                if (str_starts_with($key, $prefix.':')) {
                    $normalized = substr($key, strlen($prefix) + 1);
                    if (is_string($normalized) && $normalized !== '') {
                        $out[$normalized] = $value;
                    }
                }
            }
        }

        if (strtolower((string) $connection->provider) === 'sabre') {
            $out['client_id'] = (string) ($out['client_id'] ?? $out['username'] ?? $out['user_id'] ?? $out['userid'] ?? '');
            $out['client_secret'] = (string) ($out['client_secret'] ?? $out['password'] ?? $out['passcode'] ?? '');
        }

        if ($out === [] && $allowResolverFallback) {
            $resolved = $this->credentialResolver->forProvider($connection->provider);
            $out['client_id'] = $resolved->clientId;
            $out['client_secret'] = $resolved->clientSecret;
        }

        return $out;
    }

    private function authServiceForProvider(string $provider): TravelportAuthService|SabreAuthService|AmadeusAuthService
    {
        return match ($provider) {
            'travelport' => $this->travelportAuth,
            'sabre' => $this->sabreAuth,
            'amadeus' => $this->amadeusAuth,
            default => throw new RuntimeException('Unsupported provider: '.$provider),
        };
    }

    private function isStubMode(): bool
    {
        if ((bool) config('integrations.health_checks.force_live_in_tests', false)) {
            return false;
        }

        return config('integrations.driver') === 'stub' || app()->environment('testing');
    }

    private function normalizeError(Throwable $e, string $provider): string
    {
        $providerLabel = AmadeusSelfServiceProviderAlias::matches($provider)
            ? 'Amadeus Self Service'
            : ucfirst(strtolower(trim($provider)));

        if ($e instanceof SupplierIntegrationException) {
            $status = $e->apiError?->httpStatus;
            if ($status !== null) {
                return sprintf('%s connection test failed with HTTP %d: %s', $providerLabel, $status, mb_substr($e->getMessage(), 0, 500));
            }
        }

        $message = trim($e->getMessage());
        if ($message === '') {
            return sprintf('%s connection test failed due to an unexpected provider error.', $providerLabel);
        }

        return mb_substr($message, 0, 2000);
    }

    /**
     * @template T
     *
     * @param  callable(DuffelClient): T  $callback
     * @return T
     */
    private function executeWithDuffelClientToken(string $token, string $baseUrl, callable $callback): mixed
    {
        $auth = new class($token) implements AuthTokenProviderInterface
        {
            public function __construct(
                private readonly string $token,
            ) {
            }

            public function getAccessToken(): string
            {
                return $this->token;
            }

            public function providerCode(): string
            {
                return 'duffel';
            }

            public function refreshTokenIfNeeded(): void
            {
            }

            public function invalidateCachedToken(): void
            {
            }

            public function executeWithAuthRetry(callable $operation): mixed
            {
                return $operation();
            }
        };

        $http = new SupplierJsonHttpClient($auth, 'duffel', $baseUrl);
        $client = new DuffelClient($auth, $http);

        return $callback($client);
    }

    private function maskTokenPrefix(string $token): string
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return 'missing';
        }

        return substr($trimmed, 0, min(strlen($trimmed), 12)).'...';
    }

}
