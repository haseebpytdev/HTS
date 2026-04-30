<?php

namespace App\Integrations\Shared\Concerns;

use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderRateLimitException;
use App\Integrations\Shared\Exceptions\ProviderTransportException;

trait HandlesProviderErrors
{
    protected function throwForHttpStatus(string $providerCode, int $status, string $body): void
    {
        if ($status === 401) {
            throw new ProviderAuthException($body, $providerCode, 401);
        }
        if ($status === 429) {
            throw new ProviderRateLimitException($body, $providerCode, null);
        }
        if ($status >= 500 && $status < 600) {
            throw new ProviderTransportException($body, $providerCode);
        }
    }
}
