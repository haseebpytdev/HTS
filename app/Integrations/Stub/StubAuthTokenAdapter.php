<?php

namespace App\Integrations\Stub;

use App\Contracts\Integrations\AuthTokenProviderInterface;

final class StubAuthTokenAdapter implements AuthTokenProviderInterface
{
    public function providerCode(): string
    {
        return 'stub';
    }

    public function getAccessToken(): string
    {
        return 'stub-access-token';
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
}
