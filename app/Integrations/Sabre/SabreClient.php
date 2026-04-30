<?php

namespace App\Integrations\Sabre;

use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\SupplierHttpResponse;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Shared\BaseApiClient;

/**
 * Sabre API entry point: JSON HTTP via {@see SupplierJsonHttpClientInterface}.
 */
final class SabreClient extends BaseApiClient
{
    public function __construct(
        SabreAuthService $auth,
        private readonly SupplierJsonHttpClientInterface $jsonHttp,
        private readonly SabreSoapClient $soapHttp,
    ) {
        parent::__construct($auth);
    }

    public function providerCode(): string
    {
        return 'sabre';
    }

    public function json(): SupplierJsonHttpClientInterface
    {
        return $this->jsonHttp;
    }

    public function baseUrl(): ?string
    {
        return $this->jsonHttp->baseUrl();
    }

    public function soap(): SabreSoapClient
    {
        return $this->soapHttp;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function bargainFinderMaxSearch(array $payload, array $headers = []): SupplierHttpResponse
    {
        if (! $this->isRestSearchEnabled()) {
            throw new SupplierIntegrationException(
                message: 'Sabre REST BFM search is disabled.',
                supplierCode: 'SABRE_BFM_REST_DISABLED',
                normalizedCode: 'integration_provider_unavailable',
                apiError: new ApiErrorData(
                    code: 'integration_provider_unavailable',
                    message: 'Sabre REST BFM search is disabled.',
                    supplierCode: 'SABRE_BFM_REST_DISABLED',
                    httpStatus: 503,
                ),
            );
        }
        $endpoint = (string) config('sabre.endpoints.flight_search', '/v5/offers/shop');

        return $this->jsonHttp->post($endpoint, $payload, $headers);
    }

    public function isRestSearchEnabled(): bool
    {
        return (bool) config('sabre.live_enabled', false)
            && (bool) config('sabre.rest_search_enabled', false);
    }
}
