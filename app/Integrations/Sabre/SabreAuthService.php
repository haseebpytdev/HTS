<?php

namespace App\Integrations\Sabre;

use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\CachedSupplierToken;
use App\Integrations\Shared\AbstractSupplierAuthService;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Shared\SupplierTokenCache;
use App\Repositories\IntegrationConnectionRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Services\Integrations\ProviderCredentialResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sabre OAuth token (REST session). Central cache; refresh before expiry.
 */
final class SabreAuthService extends AbstractSupplierAuthService
{
    public function __construct(
        SupplierTokenCache $tokenCache,
        IntegrationConnectionRepository $connectionRepository,
        IntegrationTokenRepository $tokenRepository,
        ProviderCredentialResolver $credentialResolver,
    ) {
        parent::__construct($tokenCache, $connectionRepository, $tokenRepository, $credentialResolver);
    }

    public function providerCode(): string
    {
        return 'sabre';
    }

    protected function fetchNewToken(): CachedSupplierToken
    {
        $resolved = $this->credentialResolver->forProvider(
            providerCode: 'sabre',
            tenantId: null,
            agencyId: null,
            operation: 'search',
            runtimeEnvironmentOverride: 'sandbox',
        );
        $base = $resolved->baseUrl;
        $path = $this->tokenPath();

        if ($base === null || $base === '' || ! $resolved->isConfigured()) {
            throw $this->authFailure(
                code: 'sabre_credentials_missing',
                message: 'Sabre OAuth credentials are missing for the selected environment.',
                httpStatus: 503,
            );
        }

        $clientId = trim($resolved->clientId);
        $clientSecret = trim($resolved->clientSecret);
        $basicAuthHeader = $this->buildBasicAuthHeader($clientId, $clientSecret);
        $basicAuthValue = substr($basicAuthHeader, 6);
        $userIdPrefix = $clientId === '' ? 'missing' : substr($clientId, 0, min(strlen($clientId), 6)).'...';

        Log::info('integrations.sabre.auth_debug', [
            'provider' => 'sabre',
            'base_url' => $base,
            'user_id_prefix' => $userIdPrefix,
            'password_present' => $clientSecret !== '',
            'has_client_id' => $clientId !== '',
            'has_client_secret' => $clientSecret !== '',
            'source' => $resolved->integrationConnectionId !== null ? 'resolver' : 'config',
            'auth_scheme' => 'Basic',
            'auth_header_prefix' => substr($basicAuthValue, 0, 10).'...',
            'form_encoded' => true,
            'body_shape' => 'grant_type=client_credentials',
        ]);

        try {
            $tokenExchange = $this->executeTokenRequest(
                baseUrl: $base,
                clientId: $clientId,
                clientSecret: $clientSecret,
            );
            $response = $tokenExchange['response'];
        } catch (Throwable $e) {
            throw $this->authFailure(
                code: 'sabre_token_transport_error',
                message: 'Sabre token request failed due to a transport error.',
                httpStatus: 502,
                previous: $e,
            );
        }

        if ($response->failed()) {
            Log::warning('integrations.sabre.token_create_failed', [
                'provider' => 'sabre',
                'http_status' => $response->status(),
                'base_url' => $base,
                'user_id_prefix' => $userIdPrefix,
                'password_present' => $clientSecret !== '',
                'auth_scheme' => 'Basic',
                'form_encoded' => true,
                'error_body_excerpt' => mb_substr((string) $response->body(), 0, 1000),
            ]);

            throw $this->authFailure(
                code: 'sabre_token_http_error',
                message: sprintf('Sabre OAuth Token Create failed with HTTP %d.', $response->status()),
                httpStatus: $response->status() === 401 || $response->status() === 403 ? 401 : 502,
            );
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (! is_array($json) || ! isset($json['access_token'])) {
            throw $this->authFailure(
                code: 'sabre_token_response_invalid',
                message: 'Sabre OAuth response is missing access_token.',
                httpStatus: 502,
            );
        }

        $expiresIn = (int) ($json['expires_in'] ?? 3600);

        return new CachedSupplierToken(
            accessToken: (string) $json['access_token'],
            expiresAt: CarbonImmutable::now()->addSeconds($expiresIn),
            tokenType: (string) ($json['token_type'] ?? 'Bearer'),
            refreshToken: isset($json['refresh_token']) ? (string) $json['refresh_token'] : null,
        );
    }

    /**
     * @return array<string, string>
     */
    private function tokenPayload(): array
    {
        return [
            'grant_type' => 'client_credentials',
        ];
    }

    public function tokenPath(): string
    {
        return '/v2/auth/token';
    }

    public function buildBasicAuthHeader(string $clientId, string $clientSecret): string
    {
        $encodedUser = base64_encode(trim($clientId));
        $encodedPassword = base64_encode(trim($clientSecret));
        $basicAuthValue = base64_encode($encodedUser.':'.$encodedPassword);

        return 'Basic '.$basicAuthValue;
    }

    /**
     * Shared Sabre token transport method used by runtime and diagnostics.
     *
     * @return array{response: Response, latency_ms: int, url: string, correlation_id: string}
     */
    public function executeTokenRequest(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        ?string $correlationId = null,
        ?int $timeoutSeconds = null,
    ): array {
        $correlationId = $correlationId ?? (string) \Illuminate\Support\Str::uuid();
        $url = rtrim(trim($baseUrl), '/').$this->tokenPath();
        $timeoutSeconds = $timeoutSeconds ?? (int) config('integrations.http_timeout_seconds', 30);
        $startedAt = microtime(true);

        $response = Http::withBody(
            content: http_build_query($this->tokenPayload()),
            contentType: 'application/x-www-form-urlencoded'
        )
            ->timeout($timeoutSeconds)
            ->withHeaders([
                'Authorization' => $this->buildBasicAuthHeader($clientId, $clientSecret),
                'Accept' => 'application/json',
                'X-Correlation-ID' => $correlationId,
            ])
            ->post($url);

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'response' => $response,
            'latency_ms' => $latencyMs,
            'url' => $url,
            'correlation_id' => $correlationId,
        ];
    }

    private function authFailure(string $code, string $message, int $httpStatus, ?Throwable $previous = null): SupplierIntegrationException
    {
        return new SupplierIntegrationException(
            message: $message,
            supplierCode: strtoupper($code),
            normalizedCode: 'supplier_auth_failed',
            apiError: new ApiErrorData(
                code: 'supplier_auth_failed',
                message: $message,
                supplierCode: strtoupper($code),
                httpStatus: $httpStatus,
            ),
            previous: $previous,
        );
    }

}
