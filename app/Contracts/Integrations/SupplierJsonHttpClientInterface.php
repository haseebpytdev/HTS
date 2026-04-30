<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\SupplierHttpResponse;

/**
 * Raw JSON HTTP transport for supplier APIs (Bearer auth, timeouts, JSON encode/decode).
 *
 * Vendor *Client facades compose this; adapters call the facade rather than Laravel Http directly.
 */
interface SupplierJsonHttpClientInterface extends IdentifiesIntegrationProvider
{
    public function baseUrl(): ?string;

    public function get(string $uri, array $query = [], array $headers = []): SupplierHttpResponse;

    public function post(string $uri, array $json = [], array $headers = []): SupplierHttpResponse;

    public function put(string $uri, array $json = [], array $headers = []): SupplierHttpResponse;

    public function patch(string $uri, array $json = [], array $headers = []): SupplierHttpResponse;

    public function delete(string $uri, array $query = [], array $headers = []): SupplierHttpResponse;
}
