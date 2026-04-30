<?php

namespace App\Services\Async;

use App\Models\AsyncTaskRun;
use Illuminate\Database\Eloquent\Model;

final class AsyncTaskTracker
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(string $taskType, ?Model $reference = null, ?int $requestedByUserId = null, array $payload = []): AsyncTaskRun
    {
        return AsyncTaskRun::query()->create([
            'task_type' => $taskType,
            'status' => 'queued',
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'requested_by_user_id' => $requestedByUserId,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(AsyncTaskRun $run, array $result = []): void
    {
        $run->update([
            'status' => 'completed',
            'finished_at' => now(),
            'result' => $result,
            'error_message' => null,
        ]);
    }

    public function markRunning(AsyncTaskRun $run): void
    {
        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
        ]);
    }

    public function markFailed(AsyncTaskRun $run, string $error): void
    {
        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $error,
        ]);
    }
}
