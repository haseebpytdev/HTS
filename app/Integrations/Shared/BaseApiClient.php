<?php

namespace App\Integrations\Shared;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\IdentifiesIntegrationProvider;

/**
 * Base for vendor HTTP clients: shared timeout and auth wiring (subclasses supply base URL).
 */
abstract class BaseApiClient implements IdentifiesIntegrationProvider
{
    public function __construct(
        protected readonly AuthTokenProviderInterface $auth,
    ) {
    }

    abstract public function providerCode(): string;

    protected function httpTimeoutSeconds(): int
    {
        return (int) config('integrations.http_timeout_seconds', 30);
    }
}
