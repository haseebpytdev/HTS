<?php

namespace App\Contracts\Integrations;

/**
 * OAuth / session token acquisition and refresh for supplier APIs.
 *
 * Tokens are cached centrally; {@see refreshTokenIfNeeded()} and {@see getAccessToken()} refresh
 * proactively before expiry. {@see invalidateCachedToken()} supports one retry after HTTP 401.
 */
interface AuthTokenProviderInterface extends IdentifiesIntegrationProvider
{
    public function getAccessToken(): string;

    public function refreshTokenIfNeeded(): void;

    /**
     * Drop cached token (e.g. after 401) so the next {@see getAccessToken()} fetches a new one.
     */
    public function invalidateCachedToken(): void;

    /**
     * Run an operation; on HTTP 401 from the supplier, invalidate cache and retry once.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function executeWithAuthRetry(callable $operation): mixed;
}
