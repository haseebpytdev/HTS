<?php

namespace Tests\Feature\Integration;

use App\Actions\Integrations\RecordIntegrationRawExchangeAction;
use App\Data\Integrations\IntegrationRawExchangeData;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\SupplierBookingSnapshot;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_raw_exchange_persists_request_and_response(): void
    {
        $connection = IntegrationConnection::query()->create([
            'name' => 'Stub GDS',
            'provider' => 'stub',
            'environment' => 'testing',
            'base_url' => null,
            'config' => [],
            'is_active' => true,
        ]);

        $action = app(RecordIntegrationRawExchangeAction::class);
        $log = $action->execute(new IntegrationRawExchangeData(
            provider: 'stub',
            operation: 'flight_search',
            environment: 'testing',
            correlationId: 'corr-1',
            traceId: 'trace-1',
            httpMethod: 'POST',
            url: 'https://example.test/search',
            requestHeaders: ['Content-Type' => 'application/json'],
            requestBody: ['origin' => 'KHI'],
            integrationConnectionId: $connection->id,
            statusCode: 200,
            responseHeaders: ['X-Request-Id' => 'abc'],
            responseBody: ['offers' => []],
            latencyMs: 42,
            errorCategory: null,
        ));

        $this->assertDatabaseHas('integration_request_logs', [
            'correlation_id' => 'corr-1',
            'operation' => 'flight_search',
        ]);
        $this->assertNotNull($log->responseLog);
        $this->assertSame(200, $log->responseLog->status_code);
        $this->assertSame(42, $log->responseLog->latency_ms);
    }

    public function test_normalized_snapshot_tables_accept_rows(): void
    {
        $session = SupplierSearchSession::query()->create([
            'correlation_id' => 's-1',
            'provider' => 'stub',
            'environment' => 'testing',
            'internal_request_snapshot' => ['origin' => 'KHI'],
            'search_results_summary' => ['offer_count' => 0],
            'status' => 'completed',
        ]);

        SupplierOfferSnapshot::query()->create([
            'supplier_search_session_id' => $session->id,
            'offer_key' => 'offer-1',
            'provider_offer_reference' => 'ref-1',
            'normalized_offer' => ['id' => 'offer-1'],
            'selected_fare_summary' => ['total' => 100],
            'is_selected' => false,
        ]);

        SupplierBookingSnapshot::query()->create([
            'correlation_id' => 'b-1',
            'provider' => 'stub',
            'internal_status' => 'held',
            'normalized_totals' => ['currency' => 'USD', 'total' => 99],
            'travelers_json' => [['travelerType' => 'adult']],
            'booking_summary' => ['pnr' => null],
        ]);

        IntegrationEvent::query()->create([
            'event_type' => 'search.completed',
            'correlation_id' => 's-1',
            'payload' => ['ok' => true],
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseCount('supplier_search_sessions', 1);
        $this->assertDatabaseCount('supplier_offer_snapshots', 1);
        $this->assertDatabaseCount('supplier_booking_snapshots', 1);
        $this->assertDatabaseCount('integration_events', 1);
    }
}
