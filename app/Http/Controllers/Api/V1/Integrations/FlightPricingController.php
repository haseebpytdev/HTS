<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFlightPricingRequest;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\BookingRevalidationGuard;
use App\Services\Integrations\FlightPricingOrchestrator;
use App\Services\Integrations\IntegrationOrchestrationService;
use App\Services\Integrations\TenantIntegrationAccessService;
use App\Services\Integrations\TenantProviderAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

class FlightPricingController extends Controller
{
    public function __construct(
        private readonly FlightPricingOrchestrator $flightPricingOrchestrator,
        private readonly BookingRevalidationGuard $revalidationGuard,
        private readonly IntegrationOrchestrationService $integrationOrchestration,
        private readonly TenantIntegrationAccessService $tenantAccess,
        private readonly TenantProviderAuthorizationService $tenantProviderAuthorization,
    ) {
    }

    public function store(StoreFlightPricingRequest $request): JsonResponse
    {
        $v = $request->validated();
        $opaque = $v['opaque_context'] ?? [];
        $correlationId = $v['correlation_id'] ?? Str::uuid()->toString();
        $providers = isset($v['providers']) && is_array($v['providers']) ? array_values($v['providers']) : null;
        $allowFallback = (bool) ($v['allow_fallback'] ?? true);
        $providerOverride = isset($v['provider']) ? (string) $v['provider'] : null;

        try {
            $policy = $this->tenantAccess->enforcePricingPolicy(
                tenantId: isset($v['tenant_id']) ? (int) $v['tenant_id'] : null,
                agencyId: isset($v['agency_id']) ? (int) $v['agency_id'] : null,
                providerOverride: $providerOverride,
                providers: $providers,
                allowFallback: $allowFallback,
            );
            $providerOverride = $policy['provider_override'];
            $providers = $policy['providers'];
            $allowFallback = $policy['allow_fallback'];
            $tenantId = isset($v['tenant_id']) ? (int) $v['tenant_id'] : null;
            $agencyId = isset($v['agency_id']) ? (int) $v['agency_id'] : null;

            $providers = $this->integrationOrchestration->resolveAuthorizedProviderOrder(
                tenantId: $tenantId,
                agencyId: $agencyId,
                operation: 'pricing',
                primaryOverride: $providerOverride,
                requestedProviders: $providers,
            );
            $this->tenantProviderAuthorization->authorizeProvider(
                tenantId: $tenantId,
                agencyId: $agencyId,
                provider: (string) $providerOverride,
                operation: 'pricing',
            );
            $result = $this->flightPricingOrchestrator->revalidateFareWithFallback(
                offerReference: $v['offer_reference'],
                opaqueContext: is_array($opaque) ? $opaque : [],
                correlationId: $correlationId,
                providerOverride: $providerOverride,
                providers: $providers,
                allowFallback: $allowFallback,
            );

            $driver = $result['driver'];
            $price = $result['price'];
            $this->revalidationGuard->recordRevalidationResult(
                offerReference: $v['offer_reference'],
                driver: $driver,
                status: $price->status,
                correlationId: $correlationId,
                totalAmount: $price->totalAmount,
                currency: $price->currency,
            );

            return response()->json([
                'data' => array_merge(
                    [
                        'driver' => $driver,
                        'correlation_id' => $correlationId,
                        'failed_providers' => $result['failed_providers'],
                    ],
                    $price->jsonSerialize()
                ),
            ]);
        } catch (SupplierIntegrationException $e) {
            return $this->errorResponse($e->apiError ?? new ApiErrorData(
                code: $e->normalizedCode,
                message: $e->getMessage(),
                supplierCode: $e->supplierCode,
                correlationId: $correlationId,
            ));
        } catch (Throwable) {
            return $this->errorResponse(new ApiErrorData(
                code: 'integration_unavailable',
                message: 'Supplier integration temporarily unavailable.',
                supplierCode: 'UNHANDLED_INTEGRATION_ERROR',
                correlationId: $correlationId,
                httpStatus: 502,
            ));
        }
    }

    private function errorResponse(ApiErrorData $error): JsonResponse
    {
        return response()->json([
            'error' => $error->jsonSerialize(),
        ], $error->httpStatus ?? 502);
    }
}
