<?php

namespace App\Jobs;

use App\Data\Integrations\FlightSearchRequestData;
use App\Models\AsyncTaskRun;
use App\Services\Async\AsyncTaskTracker;
use App\Services\Integrations\FlightSearchOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RetrySupplierSearchJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{origin:string,destination:string,departure_date:string,adults:int,children:int,infants:int,provider?:string}  $payload
     */
    public function __construct(
        public readonly int $taskRunId,
        public readonly array $payload,
    ) {
    }

    public function handle(FlightSearchOrchestrator $orchestrator, AsyncTaskTracker $tracker): void
    {
        $task = AsyncTaskRun::query()->find($this->taskRunId);
        if (! $task) {
            return;
        }
        $tracker->markRunning($task);

        try {
            $request = new FlightSearchRequestData(
                origin: strtoupper($this->payload['origin']),
                destination: strtoupper($this->payload['destination']),
                departureDate: $this->payload['departure_date'],
                adults: (int) ($this->payload['adults'] ?? 1),
                children: (int) ($this->payload['children'] ?? 0),
                infants: (int) ($this->payload['infants'] ?? 0),
            );
            $offers = $orchestrator->search($request, null, $this->payload['provider'] ?? null, false);
            $tracker->markCompleted($task, [
                'offer_count' => count($offers),
            ]);
        } catch (Throwable $e) {
            $tracker->markFailed($task, $e->getMessage());
        }
    }
}
