<?php

namespace App\Http\Controllers\Frontend;

use App\Data\Integrations\FlightSearchRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\SearchFrontendFlightsRequest;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Currency\DisplayCurrencyResolver;
use App\Services\Frontend\FlightResultsFilterService;
use App\Services\Integrations\FlightSearchOrchestrator;
use App\Services\Integrations\IntegrationOrchestrationService;
use App\Services\Integrations\TenantIntegrationAccessService;
use App\ViewModels\Frontend\FlightSearchResultViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FlightSearchController extends Controller
{
    public function __construct(
        private readonly FlightSearchOrchestrator $flightSearchOrchestrator,
        private readonly IntegrationOrchestrationService $integrationOrchestration,
        private readonly TenantIntegrationAccessService $tenantAccess,
        private readonly DisplayCurrencyResolver $displayCurrencyResolver,
        private readonly FlightSearchResultViewModel $resultViewModel,
        private readonly FlightResultsFilterService $flightResultsFilterService,
    ) {
    }

    public function search(): View
    {
        $displayCurrency = $this->displayCurrencyResolver->resolve(request());

        return view('frontend.flights.results', [
            'hasSearched' => false,
            'offers' => [],
            'driver' => null,
            'integrationMode' => null,
            'correlationId' => null,
            'errorMessage' => null,
            'displayCurrency' => $displayCurrency['currency'],
            'displayCurrencySource' => $displayCurrency['source'],
            'displayCurrencyOptions' => $this->displayCurrencyResolver->listSelectableCurrencies(),
        ]);
    }

    public function results(SearchFrontendFlightsRequest $request): View
    {
        $validated = $request->validated();
        $displayCurrency = $this->displayCurrencyResolver->resolve($request);
        $origin = strtoupper((string) $validated['origin']);
        $destination = strtoupper((string) $validated['destination']);
        $correlationId = Str::uuid()->toString();
        [$adults, $children, $infants] = $this->passengerBreakdown((string) $validated['passengers']);
        $provider = isset($validated['provider']) ? strtolower((string) $validated['provider']) : null;

        $cabinClass = isset($validated['cabin_class']) ? (string) $validated['cabin_class'] : null;

        $criteria = new FlightSearchRequestData(
            origin: $origin,
            destination: $destination,
            departureDate: (string) $validated['departure_date'],
            adults: $adults,
            children: $children,
            infants: $infants,
            cabinClass: $cabinClass,
        );

        try {
            $policy = $this->tenantAccess->enforceSearchPolicy(
                tenantId: null,
                agencyId: null,
                providerOverride: $provider,
                providers: null,
                allowFallback: true,
                allowMultiProvider: true,
            );
            $authorizedProviders = $this->integrationOrchestration->resolveAuthorizedProviderOrder(
                tenantId: $policy['tenant_id'],
                agencyId: null,
                operation: 'search',
                primaryOverride: $policy['provider_override'],
                requestedProviders: $policy['providers'],
                respectRuntimeSingleProvider: false,
            );
            if ($authorizedProviders === []) {
                throw new SupplierIntegrationException(
                    message: 'No active provider is available for flight search.',
                    supplierCode: 'NO_AUTHORIZED_PROVIDER',
                    normalizedCode: 'integration_provider_unavailable',
                );
            }
            $driver = $authorizedProviders[0];
            $runtimeResolution = $this->integrationOrchestration->runtimeResolutionForOperation(
                tenantId: $policy['tenant_id'],
                agencyId: null,
                operation: 'search'
            );
            $integrationMode = strtolower((string) data_get($runtimeResolution, 'providers.'.$driver.'.module_environment', 'production'));
            if ((bool) config('app.debug', false) || (bool) config('integrations.debug_duffel_auth', false)) {
                Log::info('integrations.duffel.frontend.runtime_selected', [
                    'provider' => $driver,
                    'tenant_id' => $policy['tenant_id'],
                    'authorized_providers' => $authorizedProviders,
                    'integration_mode' => in_array($integrationMode, ['sandbox', 'production'], true) ? $integrationMode : 'production',
                    'correlation_id' => $correlationId,
                ]);
            }
            $searchResult = $this->flightSearchOrchestrator->searchAcrossProviders(
                request: $criteria,
                correlationId: $correlationId,
                providerOverride: $policy['provider_override'],
                providers: $authorizedProviders,
                allowFallback: (bool) $policy['allow_fallback'],
            );
            $offers = $searchResult['offers'];
            $finalProvider = $searchResult['final_provider'] ?? $driver;
            if (is_string($finalProvider) && $finalProvider !== '') {
                $driver = $finalProvider;
            }
            $providerChain = (array) ($searchResult['requested_providers'] ?? []);
            $providerAttempts = (array) ($searchResult['used_providers'] ?? []);
            $fallbackAttempts = max(0, count($providerAttempts) - 1);
            Log::info('integrations.frontend.search.provider_chain', [
                'correlation_id' => $correlationId,
                'provider_chain' => $providerChain,
                'provider_attempts' => $providerAttempts,
                'fallback_attempts' => $fallbackAttempts,
                'final_provider' => $driver,
                'search_status' => $searchResult['search_status'] ?? null,
            ]);
            $presentedOffers = array_map(
                fn ($offer): array => $this->resultViewModel->present(
                    offer: $offer->jsonSerialize(),
                    displayCurrency: $displayCurrency['currency'],
                ),
                $offers
            );
            $filtering = $this->flightResultsFilterService->apply($presentedOffers, $request->query());
            $offersPagination = $this->paginateOffers($filtering['offers'], $request);
            $providersUsed = $providerAttempts;
            $from = $offersPagination->total() > 0
                ? (($offersPagination->currentPage() - 1) * $offersPagination->perPage()) + 1
                : 0;
            $to = $offersPagination->total() > 0
                ? min($offersPagination->currentPage() * $offersPagination->perPage(), $offersPagination->total())
                : 0;
            $viewData = [
                'hasSearched' => true,
                'offers' => $offersPagination->items(),
                'offersPagination' => $offersPagination,
                'driver' => $driver,
                'integrationMode' => in_array($integrationMode, ['sandbox', 'production'], true) ? $integrationMode : 'production',
                'correlationId' => $correlationId,
                'errorMessage' => null,
                'displayCurrency' => $displayCurrency['currency'],
                'displayCurrencySource' => $displayCurrency['source'],
                'resultsSummary' => [
                    'total_offers' => $filtering['counts']['total'],
                    'visible_offers' => $filtering['counts']['filtered'],
                    'page_from' => $from,
                    'page_to' => $to,
                    'route' => $origin.' → '.$destination,
                    'providers' => $providersUsed === [] ? [$driver] : $providersUsed,
                ],
                'filterState' => $filtering['applied'],
                'filterOptions' => $filtering['options'],
                'displayCurrencyOptions' => $this->displayCurrencyResolver->listSelectableCurrencies(),
            ];

            if ($request->boolean('append') || $request->ajax() || $request->expectsJson()) {
                $appendPayload = [
                    'data' => [
                        'html' => view('frontend.flights.partials.offers-list', [
                            'offers' => $offersPagination->items(),
                            'driver' => $driver,
                            'correlationId' => $correlationId,
                        ])->render(),
                        'has_more' => $offersPagination->hasMorePages(),
                        'next_page_url' => $offersPagination->appends($request->query())->nextPageUrl(),
                        'page' => $offersPagination->currentPage(),
                        'per_page' => $offersPagination->perPage(),
                        'summary' => $viewData['resultsSummary'],
                    ],
                ];
                $json = json_encode($appendPayload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                if (! is_string($json)) {
                    $json = json_encode([
                        'data' => [
                            'html' => '',
                            'has_more' => false,
                            'next_page_url' => null,
                            'page' => $offersPagination->currentPage(),
                            'per_page' => $offersPagination->perPage(),
                            'summary' => $viewData['resultsSummary'],
                        ],
                    ]) ?: '{"data":{"html":"","has_more":false,"next_page_url":null}}';
                }

                return response($json, 200, ['Content-Type' => 'application/json']);
            }

            return view('frontend.flights.results', $viewData);
        } catch (SupplierIntegrationException $exception) {
            return view('frontend.flights.results', [
                'hasSearched' => true,
                'offers' => [],
                'driver' => $provider,
                'integrationMode' => null,
                'correlationId' => $correlationId,
                'errorMessage' => $exception->getMessage(),
                'displayCurrency' => $displayCurrency['currency'],
                'displayCurrencySource' => $displayCurrency['source'],
                'displayCurrencyOptions' => $this->displayCurrencyResolver->listSelectableCurrencies(),
            ]);
        } catch (Throwable) {
            return view('frontend.flights.results', [
                'hasSearched' => true,
                'offers' => [],
                'driver' => $provider,
                'integrationMode' => null,
                'correlationId' => $correlationId,
                'errorMessage' => 'Flight search is temporarily unavailable. Please try again in a few minutes.',
                'displayCurrency' => $displayCurrency['currency'],
                'displayCurrencySource' => $displayCurrency['source'],
                'displayCurrencyOptions' => $this->displayCurrencyResolver->listSelectableCurrencies(),
            ]);
        }
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function passengerBreakdown(string $selection): array
    {
        return match ($selection) {
            '1' => [1, 0, 0],
            '3' => [2, 1, 0],
            '4' => [2, 1, 1],
            default => [2, 0, 0],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    private function paginateOffers(array $offers, Request $request): LengthAwarePaginator
    {
        $allowedPerPage = [30];
        $perPage = (int) $request->query('per_page', 30);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 30;
        }

        $page = max(1, (int) $request->query('page', 1));
        $total = count($offers);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($offers, $offset, $perPage);

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }
}
