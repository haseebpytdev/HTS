<?php

namespace App\Services\Integrations;

use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Jobs\RetrySupplierSearchJob;
use App\Services\Async\AsyncTaskTracker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class FlightSearchOrchestrator
{
    public function __construct(
        private readonly IntegrationOrchestrationService $orchestration,
        private readonly IntegrationFlowRecorder $recorder,
        private readonly FlightOfferComparisonEngine $comparisonEngine,
        private readonly AsyncTaskTracker $taskTracker,
    ) {
    }

    /**
     * @return list<\App\Data\Integrations\FlightOfferData>
     */
    public function search(
        FlightSearchRequestData $request,
        ?string $correlationId = null,
        ?string $providerOverride = null,
        bool $queueRetryOnFailure = true
    ): array
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'search');
        $startedAt = microtime(true);

        if ((bool) config('app.debug', false)) {
            Log::debug('integrations.flight_search.orchestrator.request', [
                'correlation_id' => $cid,
                'driver' => $driver,
                'origin' => $request->origin,
                'destination' => $request->destination,
                'departure_date' => $request->departureDate,
                'cabin_class' => $request->cabinClass,
                'adults' => $request->adults,
                'children' => $request->children,
                'infants' => $request->infants,
            ]);
        }

        $this->recorder->beginFlightSearch($cid, $driver, $request);

        try {
            $offers = $this->orchestration->flightSearch($driver)->searchFlights($request);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recorder->completeFlightSearch($cid, $driver, $offers, $latencyMs);

            return $offers;
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recorder->failFlightSearch($cid, $driver, $e, $latencyMs);
            if ($queueRetryOnFailure) {
                $task = $this->taskTracker->create('supplier_search_retry', null, auth()->id() ? (int) auth()->id() : null, [
                    'correlation_id' => $cid,
                    'provider' => $driver,
                    'origin' => $request->origin,
                    'destination' => $request->destination,
                    'departure_date' => $request->departureDate,
                    'adults' => $request->adults,
                    'children' => $request->children,
                    'infants' => $request->infants,
                ]);
                RetrySupplierSearchJob::dispatch($task->id, [
                    'correlation_id' => $cid,
                    'provider' => $driver,
                    'origin' => $request->origin,
                    'destination' => $request->destination,
                    'departure_date' => $request->departureDate,
                    'adults' => $request->adults,
                    'children' => $request->children,
                    'infants' => $request->infants,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Multi-provider orchestration with normalization-preserving merge + compare.
     *
     * @param  list<string>|null  $providers
     * @return array{
     *   correlation_id: string,
     *   requested_providers: list<string>,
     *   used_providers: list<string>,
     *   failed_providers: list<array{provider: string, error: string}>,
     *   search_status: string,
     *   final_provider: ?string,
     *   offers: list<\App\Data\Integrations\FlightOfferData>,
     *   comparison: array{cheapest: ?array<string, mixed>, fastest: ?array<string, mixed>, best: ?array<string, mixed>}
     * }
     */
    public function searchAcrossProviders(
        FlightSearchRequestData $request,
        ?string $correlationId = null,
        ?string $providerOverride = null,
        ?array $providers = null,
        bool $allowFallback = true
    ): array {
        $cid = $correlationId ?? Str::uuid()->toString();
        $orderedDrivers = $this->orchestration->resolveProviderOrder($providerOverride, $providers, 'search');
        Log::info('integrations.search.provider_chain', [
            'correlation_id' => $cid,
            'provider_chain' => $orderedDrivers,
            'allow_fallback' => $allowFallback,
        ]);

        $offers = [];
        $usedProviders = [];
        $failedProviders = [];
        $searchStatus = 'successful_empty';
        $finalProvider = null;

        foreach ($orderedDrivers as $index => $driver) {
            $startedAt = microtime(true);
            $this->recorder->beginFlightSearch($cid, $driver, $request);

            try {
                $providerOffers = $this->orchestration->flightSearch($driver)->searchFlights($request);
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->recorder->completeFlightSearch($cid, $driver, $providerOffers, $latencyMs);

                $offers = array_merge($offers, $providerOffers);
                $usedProviders[] = $driver;
                $finalProvider = $driver;

                if ($providerOffers !== []) {
                    $searchStatus = 'successful_with_offers';
                    break;
                }

                if (! $allowFallback && $index === 0) {
                    break;
                }
            } catch (Throwable $e) {
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->recorder->failFlightSearch($cid, $driver, $e, $latencyMs);
                $failedProviders[] = [
                    'provider' => $driver,
                    'error' => $e->getMessage(),
                ];
                if ($this->shouldBlockFallbackForError($e)) {
                    throw $e;
                }
                $task = $this->taskTracker->create('supplier_search_retry', null, auth()->id() ? (int) auth()->id() : null, [
                    'correlation_id' => $cid,
                    'provider' => $driver,
                    'origin' => $request->origin,
                    'destination' => $request->destination,
                    'departure_date' => $request->departureDate,
                    'adults' => $request->adults,
                    'children' => $request->children,
                    'infants' => $request->infants,
                ]);
                RetrySupplierSearchJob::dispatch($task->id, [
                    'correlation_id' => $cid,
                    'provider' => $driver,
                    'origin' => $request->origin,
                    'destination' => $request->destination,
                    'departure_date' => $request->departureDate,
                    'adults' => $request->adults,
                    'children' => $request->children,
                    'infants' => $request->infants,
                ]);

                // In no-fallback mode, keep old behavior and bubble first failure.
                if (! $allowFallback && $index === 0) {
                    throw $e;
                }
            }
        }

        return [
            'correlation_id' => $cid,
            'requested_providers' => $orderedDrivers,
            'used_providers' => $usedProviders,
            'failed_providers' => $failedProviders,
            'search_status' => $searchStatus,
            'final_provider' => $finalProvider,
            'offers' => $offers,
            'comparison' => $this->comparisonEngine->compare($offers),
        ];
    }

    private function shouldBlockFallbackForError(Throwable $e): bool
    {
        if (! $e instanceof SupplierIntegrationException) {
            return false;
        }

        if (! in_array($e->normalizedCode, ['supplier_auth_failed', 'integration_provider_unavailable'], true)) {
            return false;
        }

        return ! (bool) config('integrations.search_fallback_on_auth_or_config_errors', false);
    }
}
