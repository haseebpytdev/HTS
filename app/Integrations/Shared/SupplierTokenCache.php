<?php

namespace App\Integrations\Shared;

use App\Data\Integrations\CachedSupplierToken;
use Illuminate\Contracts\Cache\Repository;

/**
 * Central cache for supplier access tokens (keyed by provider + credential environment).
 */
final class SupplierTokenCache
{
    public function __construct(
        private readonly Repository $cache
    ) {
    }

    public function get(string $provider, string $credentialEnvironment): ?CachedSupplierToken
    {
        $key = $this->key($provider, $credentialEnvironment);
        $raw = $this->cache->get($key);
        if (! is_array($raw)) {
            return null;
        }

        return CachedSupplierToken::fromArray($raw);
    }

    public function put(string $provider, string $credentialEnvironment, CachedSupplierToken $token): void
    {
        $key = $this->key($provider, $credentialEnvironment);
        $ttlSeconds = max(60, $token->expiresAt->getTimestamp() - now()->getTimestamp());
        $this->cache->put($key, $token->toArray(), $ttlSeconds);
    }

    public function forget(string $provider, string $credentialEnvironment): void
    {
        $this->cache->forget($this->key($provider, $credentialEnvironment));
    }

    private function key(string $provider, string $credentialEnvironment): string
    {
        return 'supplier_token:'.$provider.':'.$credentialEnvironment;
    }
}
