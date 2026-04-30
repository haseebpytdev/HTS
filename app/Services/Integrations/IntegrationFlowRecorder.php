<?php

namespace App\Services\Integrations;

use App\Actions\Integrations\RecordSupplierSearchSessionAction;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\PriceBreakdownData;
use App\Models\SupplierBookingSnapshot;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Phase 11.10 — Orchestration audit (integration_logs) + normalized business snapshots (search sessions, offers, booking).
 */
final class IntegrationFlowRecorder
{
    public function __construct(
        private readonly IntegrationAuditLogger $auditLogger,
        private readonly RecordSupplierSearchSessionAction $recordSearchSession,
    ) {
    }

    public function beginFlightSearch(string $correlationId, string $driver, FlightSearchRequestData $request): void
    {
        $this->auditLogger->logOrchestration('flight_search_started', $driver, [
            'correlation_id' => $correlationId,
            'request' => $request->toSnapshotArray(),
        ]);

        if (! Schema::hasTable('supplier_search_sessions')) {
            return;
        }

        $env = (string) config('integrations.credential_environment', 'production');

        $this->recordSearchSession->execute(
            correlationId: $correlationId,
            provider: $driver,
            environment: $env,
            status: 'pending',
            internalRequestSnapshot: $request->toSnapshotArray(),
            searchResultsSummary: null,
            integrationConnectionId: null,
            startedAt: now()->toIso8601String(),
            completedAt: null,
        );
    }

    /**
     * @param  list<FlightOfferData>  $offers
     */
    public function completeFlightSearch(string $correlationId, string $driver, array $offers, ?int $latencyMs = null): void
    {
        $summary = [
            'offer_count' => count($offers),
            'offer_ids' => array_map(static fn (FlightOfferData $o): string => $o->id, $offers),
        ];

        $this->auditLogger->logOrchestration('flight_search_completed', $driver, [
            'correlation_id' => $correlationId,
            'summary' => $summary,
            'latency_ms' => $latencyMs,
        ]);

        if (! Schema::hasTable('supplier_search_sessions')) {
            return;
        }

        $env = (string) config('integrations.credential_environment', 'production');
        $existing = SupplierSearchSession::query()->where('correlation_id', $correlationId)->first();

        $session = $this->recordSearchSession->execute(
            correlationId: $correlationId,
            provider: $driver,
            environment: $env,
            status: 'completed',
            internalRequestSnapshot: $existing?->internal_request_snapshot,
            searchResultsSummary: $summary,
            integrationConnectionId: $existing?->integration_connection_id,
            startedAt: $existing?->started_at?->toIso8601String(),
            completedAt: now()->toIso8601String(),
        );

        if (! Schema::hasTable('supplier_offer_snapshots')) {
            return;
        }

        SupplierOfferSnapshot::query()->where('supplier_search_session_id', $session->id)->delete();

        foreach ($offers as $offer) {
            SupplierOfferSnapshot::query()->create([
                'supplier_search_session_id' => $session->id,
                'offer_key' => $offer->id,
                'provider_offer_reference' => $offer->providerOfferReference,
                'normalized_offer' => $offer->jsonSerialize(),
                'selected_fare_summary' => null,
                'is_selected' => false,
            ]);
        }
    }

    public function failFlightSearch(string $correlationId, string $driver, Throwable $e, ?int $latencyMs = null): void
    {
        $this->auditLogger->logOrchestration('flight_search_failed', $driver, [
            'correlation_id' => $correlationId,
            'error' => $e->getMessage(),
            'latency_ms' => $latencyMs,
        ]);

        if (! Schema::hasTable('supplier_search_sessions')) {
            return;
        }

        $env = (string) config('integrations.credential_environment', 'production');

        $session = SupplierSearchSession::query()->where('correlation_id', $correlationId)->first();
        $snapshot = $session?->internal_request_snapshot;

        $this->recordSearchSession->execute(
            correlationId: $correlationId,
            provider: $driver,
            environment: $env,
            status: 'failed',
            internalRequestSnapshot: $snapshot,
            searchResultsSummary: ['error' => $e->getMessage()],
            integrationConnectionId: $session?->integration_connection_id,
            startedAt: $session?->started_at?->toIso8601String(),
            completedAt: now()->toIso8601String(),
        );
    }

    public function beginFlightPricing(string $correlationId, string $driver, string $offerReference): void
    {
        $this->auditLogger->logOrchestration('flight_pricing_started', $driver, [
            'correlation_id' => $correlationId,
            'offer_reference' => $offerReference,
        ]);
    }

    public function completeFlightPricing(string $correlationId, string $driver, PriceBreakdownData $price): void
    {
        $this->auditLogger->logOrchestration('flight_pricing_completed', $driver, [
            'correlation_id' => $correlationId,
            'offer_reference' => $price->offerReference,
            'status' => $price->status,
            'total_amount' => $price->totalAmount,
            'currency' => $price->currency,
        ]);
    }

    public function failFlightPricing(string $correlationId, string $driver, string $offerReference, Throwable $e): void
    {
        $this->auditLogger->logOrchestration('flight_pricing_failed', $driver, [
            'correlation_id' => $correlationId,
            'offer_reference' => $offerReference,
            'error' => $e->getMessage(),
        ]);
    }

    public function beginBookingCreate(string $correlationId, string $driver): void
    {
        $this->auditLogger->logOrchestration('booking_create_started', $driver, [
            'correlation_id' => $correlationId,
        ]);
    }

    public function completeBookingCreate(string $correlationId, string $driver, BookingData $booking): void
    {
        $this->auditLogger->logOrchestration('booking_create_completed', $driver, [
            'correlation_id' => $correlationId,
            'status' => $booking->status,
            'booking_reference' => $booking->bookingReference,
        ]);

        if (! Schema::hasTable('supplier_booking_snapshots')) {
            return;
        }

        SupplierBookingSnapshot::query()->create([
            'correlation_id' => $correlationId,
            'integration_connection_id' => null,
            'integration_request_log_id' => null,
            'provider' => $driver,
            'booking_reference' => $booking->bookingReference,
            'pnr' => $booking->pnr,
            'internal_status' => $booking->status,
            'normalized_totals' => $booking->totalPrice?->jsonSerialize(),
            'travelers_json' => array_map(
                static fn ($t) => $t->jsonSerialize(),
                $booking->travelers
            ),
            'booking_summary' => $booking->jsonSerialize(),
        ]);
    }

    public function failBookingCreate(string $correlationId, string $driver, Throwable $e): void
    {
        $this->auditLogger->logOrchestration('booking_create_failed', $driver, [
            'correlation_id' => $correlationId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordBookingLifecycleStage(string $correlationId, string $driver, string $stage, array $context = []): void
    {
        $this->auditLogger->logOrchestration('booking_lifecycle_stage', $driver, array_merge([
            'correlation_id' => $correlationId,
            'stage' => $stage,
        ], $context));
    }
}
