<?php

namespace App\Integrations\Shared\Concerns;

use App\Contracts\Integrations\AuthTokenProviderInterface;

trait HandlesAuthTokens
{
    abstract protected function auth(): AuthTokenProviderInterface;

    protected function bearerToken(): string
    {
        return $this->auth()->getAccessToken();
    }
}
