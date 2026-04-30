<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFlightSearchRequest;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\FlightSearchOrchestrator;
use App\Services\Integrations\IntegrationOrchestrationService;
use App\Services\Integrations\TenantIntegrationAccessService;
use App\Services\Integrations\TenantProviderAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class FlightSearchController extends Controller
{
    public function __construct(
        private readonly FlightSearchOrchestrator $flightSearchOrchestrator,
        private readonly IntegrationOrchestrationService $integrationOrchestration,
        private readonly TenantIntegrationAccessService $tenantAccess,
        private readonly TenantProviderAuthorizationService $tenantProviderAuthorization,
    ) {
    }

    public function store(StoreFlightSearchRequest $request): JsonResponse
    {
        $v = $request->validated();
        $correlationId = $v['correlation_id'] ?? Str::uuid()->toString();
        $criteria = new FlightSearchRequestData(
            origin: strtoupper($v['origin']),
            destination: strtoupper($v['destination']),
            departureDate: $v['departure_date'],
            adults: (int) ($v['adults'] ?? 1),
            children: (int) ($v['children'] ?? 0),
            infants: (int) ($v['infants'] ?? 0),
            cabinClass: isset($v['cabin_class']) ? (string) $v['cabin_class'] : null,
        );
        $isMulti = (bool) ($v['multi_provider'] ?? false);
        $providers = isset($v['providers']) && is_array($v['providers']) ? array_values($v['providers']) : null;
        $allowFallback = (bool) ($v['allow_fallback'] ?? true);

        try {
            $policy = $this->tenantAccess->enforceSearchPolicy(
                tenantId: isset($v['tenant_id']) ? (int) $v['tenant_id'] : null,
                agencyId: isset($v['agency_id']) ? (int) $v['agency_id'] : null,
                providerOverride: isset($v['provider']) ? (string) $v['provider'] : null,
                providers: $providers,
                allowFallback: $allowFallback,
                allowMultiProvider: $isMulti || ($providers !== null && $providers !== []),
            );

            $providerOverride = $policy['provider_override'];
            $providers = $policy['providers'];
            $allowFallback = $policy['allow_fallback'];
            $isMulti = $policy['allow_multi_provider'];
            $tenantId = $policy['tenant_id'];
            $agencyId = isset($v['agency_id']) ? (int) $v['agency_id'] : null;

            $ttlSeconds = max(1, (int) config('integrations.search_snapshot_ttl_seconds', 30));
            $cacheKey = $this->cacheKey(
                criteria: $criteria,
                providerOverride: $providerOverride,
                providers: $providers,
                allowFallback: $allowFallback,
                isMulti: $isMulti || ($providers !== null && $providers !== []),
            );

            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json(['data' => $cached]);
            }

            if ($isMulti || ($providers !== null && $providers !== [])) {
                $authorizedProviders = $this->integrationOrchestration->resolveAuthorizedProviderOrder(
                    tenantId: $tenantId,
                    agencyId: $agencyId,
                    operation: 'search',
                    primaryOverride: $providerOverride,
                    requestedProviders: $providers,
                );
                $result = $this->flightSearchOrchestrator->searchAcrossProviders(
                    request: $criteria,
                    correlationId: $correlationId,
                    providerOverride: $providerOverride,
                    providers: $authorizedProviders,
                    allowFallback: $allowFallback,
                );

                $payload = [
                    'driver' => $result['final_provider'],
                    'mode' => 'multi_provider',
                    'correlation_id' => $result['correlation_id'],
                    'requested_providers' => $result['requested_providers'],
                    'used_providers' => $result['used_providers'],
                    'failed_providers' => $result['failed_providers'],
                    'search_status' => $result['search_status'],
                    'final_provider' => $result['final_provider'],
                    'comparison' => $result['comparison'],
                    'offers' => array_map(
                        static fn ($offer) => $offer->jsonSerialize(),
                        $result['offers']
                    ),
                ];
                Cache::put($cacheKey, $payload, now()->addSeconds($ttlSeconds));

                return response()->json(['data' => $payload]);
            }

            $this->tenantProviderAuthorization->authorizeProvider(
                tenantId: $tenantId,
                agencyId: $agencyId,
                provider: (string) $providerOverride,
                operation: 'search',
            );
            $driver = $this->integrationOrchestration->resolveDriver($providerOverride);
            $offers = $this->flightSearchOrchestrator->search($criteria, $correlationId, $providerOverride);

            $payload = [
                'driver' => $driver,
                'mode' => 'single_provider',
                'correlation_id' => $correlationId,
                'offers' => array_map(
                    static fn ($offer) => $offer->jsonSerialize(),
                    $offers
                ),
            ];
            Cache::put($cacheKey, $payload, now()->addSeconds($ttlSeconds));

            return response()->json(['data' => $payload]);
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

    /**
     * @param  list<string>|null  $providers
     */
    private function cacheKey(
        FlightSearchRequestData $criteria,
        ?string $providerOverride,
        ?array $providers,
        bool $allowFallback,
        bool $isMulti
    ): string
    {
        $normalizedProviders = $providers !== null ? array_values($providers) : [];
        sort($normalizedProviders);

        $fingerprint = [
            'origin' => $criteria->origin,
            'destination' => $criteria->destination,
            'departure_date' => $criteria->departureDate,
            'adults' => $criteria->adults,
            'children' => $criteria->children,
            'infants' => $criteria->infants,
            'provider' => $providerOverride,
            'providers' => $normalizedProviders,
            'allow_fallback' => $allowFallback,
            'is_multi' => $isMulti,
        ];

        return 'integrations:flight_search_snapshot:'.sha1(json_encode($fingerprint, JSON_THROW_ON_ERROR));
    }

    private function errorResponse(ApiErrorData $error): JsonResponse
    {
        return response()->json([
            'error' => $error->jsonSerialize(),
        ], $error->httpStatus ?? 502);
    }
}
