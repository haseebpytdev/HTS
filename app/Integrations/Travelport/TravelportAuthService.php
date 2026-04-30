<?php

namespace App\Integrations\Travelport;

use App\Data\Integrations\CachedSupplierToken;
use App\Integrations\Shared\AbstractSupplierAuthService;
use App\Integrations\Shared\SupplierTokenCache;
use App\Repositories\IntegrationConnectionRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Services\Integrations\ProviderCredentialResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Travelport OAuth / session token. Cache + refresh-before-expiry; do not request a new token per call.
 *
 * @see https://developer.travelport.com/ (token lifetime / reuse guidance)
 */
final class TravelportAuthService extends AbstractSupplierAuthService
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
        return 'travelport';
    }

    protected function fetchNewToken(): CachedSupplierToken
    {
        $resolved = $this->credentialResolver->forProvider('travelport');
        $base = $resolved->baseUrl ?? $this->configBaseUrl();
        $path = $resolved->tokenPath ?? (string) config('travelport.token_path', '/oauth/token');

        if ($base === null || $base === '' || ! $resolved->isConfigured()) {
            return $this->unconfiguredPlaceholderToken();
        }

        $url = rtrim($base, '/').$path;

        try {
            $response = Http::asForm()
                ->timeout((int) config('integrations.http_timeout_seconds', 30))
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $resolved->clientId,
                    'client_secret' => $resolved->clientSecret,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Travelport token request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Travelport token HTTP '.$response->status().': '.$response->body());
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (! is_array($json) || ! isset($json['access_token'])) {
            throw new RuntimeException('Travelport token: missing access_token in response');
        }

        $expiresIn = (int) ($json['expires_in'] ?? 86400);

        return new CachedSupplierToken(
            accessToken: (string) $json['access_token'],
            expiresAt: CarbonImmutable::now()->addSeconds($expiresIn),
            tokenType: (string) ($json['token_type'] ?? 'Bearer'),
            refreshToken: isset($json['refresh_token']) ? (string) $json['refresh_token'] : null,
        );
    }

    private function unconfiguredPlaceholderToken(): CachedSupplierToken
    {
        return new CachedSupplierToken(
            accessToken: 'travelport-unconfigured',
            expiresAt: CarbonImmutable::now()->addHour(),
            tokenType: 'Bearer',
        );
    }

    private function configBaseUrl(): ?string
    {
        $u = trim((string) config('travelport.base_url', ''));

        return $u !== '' ? $u : null;
    }
}
