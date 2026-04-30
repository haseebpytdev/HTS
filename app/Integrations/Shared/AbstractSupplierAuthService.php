<?php

namespace App\Integrations\Shared;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Data\Integrations\CachedSupplierToken;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Models\IntegrationConnection;
use App\Repositories\IntegrationConnectionRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Shared token lifecycle: cache, optional DB mirror, proactive refresh before expiry, single-flight lock, one retry on 401.
 */
abstract class AbstractSupplierAuthService implements AuthTokenProviderInterface
{
    public function __construct(
        protected readonly SupplierTokenCache $tokenCache,
        protected readonly IntegrationConnectionRepository $connectionRepository,
        protected readonly IntegrationTokenRepository $tokenRepository,
        protected readonly ProviderCredentialResolver $credentialResolver,
    ) {
    }

    abstract public function providerCode(): string;

    /**
     * Fetch a new token from the provider (OAuth client credentials, etc.).
     */
    abstract protected function fetchNewToken(): CachedSupplierToken;

    protected function credentialEnvironment(): string
    {
        $env = (string) config('integrations.credential_environment', 'production');

        return in_array($env, ['test', 'production'], true) ? $env : 'production';
    }

    public function getAccessToken(): string
    {
        return $this->ensureFreshToken()->accessToken;
    }

    public function refreshTokenIfNeeded(): void
    {
        $this->ensureFreshToken();
    }

    public function invalidateCachedToken(): void
    {
        $this->tokenCache->forget($this->providerCode(), $this->credentialEnvironment());
        $connection = $this->resolveIntegrationConnection();
        if ($connection !== null && config('integrations.persist_tokens_to_database')) {
            $this->tokenRepository->deleteAccessTokens($connection);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function executeWithAuthRetry(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ProviderAuthException $e) {
            if (! $e->isUnauthorized()) {
                throw $e;
            }
            $this->invalidateCachedToken();

            return $operation();
        }
    }

    protected function ensureFreshToken(): CachedSupplierToken
    {
        $provider = $this->providerCode();
        $env = $this->credentialEnvironment();
        $lockKey = 'supplier_token_lock:'.$provider.':'.$env;
        $lock = Cache::lock($lockKey, 30);

        try {
            return $lock->block(10, function () use ($provider, $env): CachedSupplierToken {
                $cached = $this->tokenCache->get($provider, $env);
                if ($cached !== null && ! $this->shouldRefresh($cached)) {
                    return $cached;
                }

                $connection = $this->resolveIntegrationConnection();
                if ($connection !== null && config('integrations.persist_tokens_to_database')) {
                    $stored = $this->tokenRepository->findLatestValidAccessToken($connection);
                    if ($stored !== null && ! $this->shouldRefresh($stored)) {
                        $this->tokenCache->put($provider, $env, $stored);

                        return $stored;
                    }
                }

                $fresh = $this->fetchNewToken();
                $this->tokenCache->put($provider, $env, $fresh);
                if ($this->shouldPersistAccessToken($connection, $fresh)) {
                    $this->tokenRepository->storeAccessToken($connection, $fresh);
                }

                return $fresh;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $cached = $this->tokenCache->get($provider, $env);
            if ($cached !== null) {
                return $cached;
            }

            $connection = $this->resolveIntegrationConnection();
            if ($connection !== null && config('integrations.persist_tokens_to_database')) {
                $stored = $this->tokenRepository->findLatestValidAccessToken($connection);
                if ($stored !== null && ! $this->shouldRefresh($stored)) {
                    $this->tokenCache->put($provider, $env, $stored);

                    return $stored;
                }
            }

            $fresh = $this->fetchNewToken();
            $this->tokenCache->put($provider, $env, $fresh);
            if ($this->shouldPersistAccessToken($connection, $fresh)) {
                $this->tokenRepository->storeAccessToken($connection, $fresh);
            }

            return $fresh;
        }
    }

    protected function resolveIntegrationConnection(): ?IntegrationConnection
    {
        if (! config('integrations.use_database_credentials') && ! config('integrations.persist_tokens_to_database')) {
            return null;
        }

        return $this->connectionRepository->findActiveForProviderAndEnvironment(
            $this->providerCode(),
            $this->credentialEnvironment()
        );
    }

    protected function shouldRefresh(CachedSupplierToken $token): bool
    {
        if ($token->expiresAt->isPast()) {
            return true;
        }

        $buffer = (int) config('integrations.token_refresh_buffer_seconds', 300);

        $refreshAfter = $token->expiresAt->subSeconds($buffer);

        return now()->greaterThanOrEqualTo($refreshAfter);
    }

    protected function shouldPersistAccessToken(?IntegrationConnection $connection, CachedSupplierToken $token): bool
    {
        if ($connection === null || ! config('integrations.persist_tokens_to_database')) {
            return false;
        }

        return ! str_contains($token->accessToken, 'unconfigured');
    }
}
