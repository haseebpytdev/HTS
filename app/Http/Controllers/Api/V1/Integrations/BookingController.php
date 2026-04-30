<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\TravelerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\BookingOrchestrator;
use App\Services\Integrations\ProviderResolver;
use App\Services\Integrations\TenantIntegrationAccessService;
use App\Services\Integrations\TenantProviderAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingOrchestrator $bookingOrchestrator,
        private readonly ProviderResolver $providerResolver,
        private readonly TenantIntegrationAccessService $tenantAccess,
        private readonly TenantProviderAuthorizationService $tenantProviderAuthorization,
    ) {
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $v = $request->validated();
        $travelers = [];
        foreach ($v['travelers'] as $row) {
            $travelers[] = new TravelerData(
                travelerType: $row['traveler_type'],
                givenName: $row['given_name'],
                familyName: $row['family_name'],
                dateOfBirth: $row['date_of_birth'] ?? null,
                nationality: $row['nationality'] ?? null,
            );
        }

        $correlationId = $v['correlation_id'] ?? Str::uuid()->toString();
        try {
            $providerOverride = $this->tenantAccess->resolveProviderForOperation(
                tenantId: isset($v['tenant_id']) ? (int) $v['tenant_id'] : null,
                agencyId: isset($v['agency_id']) ? (int) $v['agency_id'] : null,
                operation: 'booking',
                provider: isset($v['provider']) ? (string) $v['provider'] : null,
            );
            $tenantId = isset($v['tenant_id']) ? (int) $v['tenant_id'] : null;
            $agencyId = isset($v['agency_id']) ? (int) $v['agency_id'] : null;
            $this->tenantProviderAuthorization->authorizeProvider($tenantId, $agencyId, $providerOverride, 'booking');
            $driver = $this->providerResolver->driverForOperation($tenantId, $agencyId, 'booking', $providerOverride);

            $booking = $this->bookingOrchestrator->create(
                new BookingCreateRequestData(
                    offerReference: $v['offer_reference'],
                    travelers: $travelers,
                    contactEmail: $v['contact_email'] ?? null,
                    contactPhone: $v['contact_phone'] ?? null,
                ),
                $correlationId,
                $providerOverride
            );
        } catch (SupplierIntegrationException $e) {
            $defaultStatus = $e->normalizedCode === 'fresh_revalidation_required' ? 422 : 502;

            return $this->errorResponse($e->apiError ?? new ApiErrorData(
                code: $e->normalizedCode,
                message: $e->getMessage(),
                supplierCode: $e->supplierCode,
                correlationId: $correlationId,
                httpStatus: $defaultStatus,
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

        return response()->json([
            'data' => array_merge(
                [
                    'driver' => $driver,
                    'correlation_id' => $correlationId,
                ],
                $booking->jsonSerialize()
            ),
        ], 201);
    }

    private function errorResponse(ApiErrorData $error): JsonResponse
    {
        return response()->json([
            'error' => $error->jsonSerialize(),
        ], $error->httpStatus ?? 502);
    }
}
