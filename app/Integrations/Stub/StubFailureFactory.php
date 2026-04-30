<?php

namespace App\Integrations\Stub;

use App\Data\Integrations\ApiErrorData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;

final class StubFailureFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     */
    public static function fromFixture(string $provider, array $fixture): SupplierIntegrationException
    {
        $error = $fixture['error'] ?? [];
        $normalizedCode = (string) ($error['normalized_code'] ?? 'integration_error');
        $supplierCode = (string) ($error['supplier_code'] ?? 'INTEGRATION_ERROR');
        $message = (string) ($error['message'] ?? 'Stub integration error.');
        $httpStatus = isset($error['http_status']) ? (int) $error['http_status'] : 502;
        $correlationId = isset($error['correlation_id']) ? (string) $error['correlation_id'] : null;

        return new SupplierIntegrationException(
            message: $message,
            supplierCode: $supplierCode,
            normalizedCode: $normalizedCode,
            supplierContext: [
                'provider' => $provider,
                'scenario' => $fixture['scenario'] ?? null,
            ],
            apiError: new ApiErrorData(
                code: $normalizedCode,
                message: $message,
                supplierCode: $supplierCode,
                correlationId: $correlationId,
                httpStatus: $httpStatus,
            ),
        );
    }
}
