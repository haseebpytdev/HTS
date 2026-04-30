<?php

namespace App\Integrations\Iati;

use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Integrations\Shared\BaseApiClient;

final class IatiClient extends BaseApiClient
{
    public function __construct(
        IatiAuthService $auth,
        private readonly SupplierJsonHttpClientInterface $jsonHttp,
    ) {
        parent::__construct($auth);
    }

    public function providerCode(): string
    {
        return 'iati';
    }

    public function json(): SupplierJsonHttpClientInterface
    {
        return $this->jsonHttp;
    }

    public function baseUrl(): ?string
    {
        return $this->jsonHttp->baseUrl();
    }
}
