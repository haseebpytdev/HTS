<?php

namespace App\Integrations\Travelport;

use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Integrations\Shared\BaseApiClient;

/**
 * Travelport API entry point: compose {@see SupplierJsonHttpClientInterface} for JSON calls.
 */
final class TravelportClient extends BaseApiClient
{
    public function __construct(
        TravelportAuthService $auth,
        private readonly SupplierJsonHttpClientInterface $jsonHttp,
    ) {
        parent::__construct($auth);
    }

    public function providerCode(): string
    {
        return 'travelport';
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
