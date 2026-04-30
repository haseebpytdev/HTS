<?php

namespace App\Console\Commands;

use App\Actions\Admin\TestIntegrationConnectionAction;
use App\Data\Integrations\FlightSearchRequestData;
use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\SupplierSearchSession;
use App\Models\Tenant;
use App\Services\Integrations\FlightSearchOrchestrator;
use App\Services\Integrations\TenantProviderAuthorizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SabreSandboxSmokeCheckCommand extends Command
{
    protected $signature = 'integrations:sabre-sandbox-smoke
        {--tenant=1 : Tenant id to validate}
        {--origin=LHR : IATA origin}
        {--destination=JFK : IATA destination}
        {--departure_date=2026-06-01 : Departure date (Y-m-d)}
        {--adults=1 : Adult passenger count}
        {--cabin_class=economy : Cabin class}
        {--check-frontend : Attempt frontend results page GET via APP_URL}';

    protected $description = 'Run Sabre sandbox smoke checks: connection test, search, request logs, search sessions, optional frontend page probe';

    public function handle(
        TestIntegrationConnectionAction $testConnection,
        TenantProviderAuthorizationService $authorizationService,
        FlightSearchOrchestrator $flightSearchOrchestrator
    ): int {
        $tenantId = (int) $this->option('tenant');
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            $this->components->error(sprintf('Tenant %d not found.', $tenantId));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Sabre sandbox smoke check for tenant #%d (%s)', $tenant->id, $tenant->name));

        $connection = IntegrationConnection::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'sabre')
            ->whereIn('environment', ['sandbox', 'test'])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
        if ($connection === null) {
            $connection = IntegrationConnection::query()
                ->whereNull('tenant_id')
                ->where('provider', 'sabre')
                ->whereIn('environment', ['sandbox', 'test'])
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();
        }

        if ($connection === null) {
            $this->components->error('No active Sabre sandbox/test integration connection found for this tenant.');

            return self::FAILURE;
        }

        $this->line('1) Testing Sabre connection...');
        $connectionResult = $testConnection->execute($connection);
        $this->table(
            ['ok', 'message', 'token_expires_at'],
            [[
                $connectionResult['ok'] ? 'yes' : 'no',
                (string) $connectionResult['message'],
                $connectionResult['token_expires_at'] ?? '-',
            ]]
        );
        if (! $connectionResult['ok']) {
            $this->components->warn('Connection test failed. Skipping downstream search checks.');

            return self::FAILURE;
        }

        $this->line('2) Authorizing tenant provider access before adapter execution...');
        try {
            $authorizationService->authorizeProvider(
                tenantId: $tenant->id,
                agencyId: null,
                provider: 'sabre',
                operation: 'search'
            );
            $this->components->info('Tenant is authorized for Sabre search.');
        } catch (Throwable $e) {
            $this->components->error('Tenant provider authorization failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $correlationId = 'sabre-smoke-'.Str::uuid()->toString();
        $this->line('3) Running Sabre flight-search orchestration...');
        $request = new FlightSearchRequestData(
            origin: strtoupper((string) $this->option('origin')),
            destination: strtoupper((string) $this->option('destination')),
            departureDate: (string) $this->option('departure_date'),
            adults: max(1, (int) $this->option('adults')),
            children: 0,
            infants: 0,
            cabinClass: (string) $this->option('cabin_class'),
        );
        try {
            $offers = $flightSearchOrchestrator->search(
                request: $request,
                correlationId: $correlationId,
                providerOverride: 'sabre',
                queueRetryOnFailure: false,
            );
            $this->components->info(sprintf('Search completed. Offer count: %d', count($offers)));
        } catch (Throwable $e) {
            $this->components->error('Search failed: '.$e->getMessage());
            $this->printDebugArtifacts($correlationId);

            return self::FAILURE;
        }

        $this->line('4) Checking integration_request_logs...');
        $log = IntegrationRequestLog::query()
            ->where('provider', 'sabre')
            ->where('correlation_id', $correlationId)
            ->latest('id')
            ->with('responseLog')
            ->first();
        if ($log === null) {
            $this->components->warn('No integration_request_logs found for this correlation id.');
        } else {
            $this->table(
                ['operation', 'url', 'status_code', 'latency_ms'],
                [[
                    $log->operation,
                    $log->url,
                    (string) ($log->responseLog?->status_code ?? '-'),
                    (string) ($log->responseLog?->latency_ms ?? '-'),
                ]]
            );
        }

        $this->line('5) Checking supplier_search_sessions...');
        $session = SupplierSearchSession::query()
            ->where('correlation_id', $correlationId)
            ->latest('id')
            ->first();
        if ($session === null) {
            $this->components->warn('No supplier_search_sessions row found for this correlation id.');
        } else {
            $this->table(
                ['status', 'provider', 'started_at', 'completed_at'],
                [[
                    $session->status,
                    $session->provider,
                    (string) $session->started_at,
                    (string) $session->completed_at,
                ]]
            );
        }

        if ((bool) $this->option('check-frontend')) {
            $this->line('6) Probing frontend flight results page...');
            $frontendUrl = rtrim((string) config('app.url', ''), '/').'/flights/results?origin='
                .urlencode($request->origin)
                .'&destination='.urlencode($request->destination)
                .'&departure_date='.urlencode($request->departureDate)
                .'&adults='.urlencode((string) $request->adults);
            if (! str_starts_with($frontendUrl, 'http')) {
                $this->components->warn('APP_URL is not configured with http/https. Skipping frontend probe.');
            } else {
                try {
                    $frontend = Http::timeout(20)->get($frontendUrl);
                    $this->table(['frontend_url', 'http_status'], [[$frontendUrl, (string) $frontend->status()]]);
                } catch (Throwable $e) {
                    $this->components->warn('Frontend probe failed: '.$e->getMessage());
                }
            }
        }

        $this->components->info('Sabre sandbox smoke check finished.');

        return self::SUCCESS;
    }

    private function printDebugArtifacts(string $correlationId): void
    {
        $log = IntegrationRequestLog::query()
            ->where('provider', 'sabre')
            ->where('correlation_id', $correlationId)
            ->latest('id')
            ->with('responseLog')
            ->first();
        if ($log !== null) {
            $this->table(
                ['operation', 'status_code', 'url'],
                [[
                    $log->operation,
                    (string) ($log->responseLog?->status_code ?? '-'),
                    $log->url,
                ]]
            );
        }
    }
}
