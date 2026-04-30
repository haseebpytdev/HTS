<?php

namespace App\Integrations\Amadeus;

use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Shared\BaseApiClient;

/**
 * Amadeus API entry point: JSON HTTP via {@see SupplierJsonHttpClientInterface}.
 */
final class AmadeusClient extends BaseApiClient
{
    public function __construct(
        AmadeusAuthService $auth,
        private readonly SupplierJsonHttpClientInterface $jsonHttp,
    ) {
        parent::__construct($auth);
    }

    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
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
