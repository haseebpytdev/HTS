<?php

namespace App\Jobs;

use App\Models\AsyncTaskRun;
use App\Models\BookingDocument;
use App\Models\BookingDocumentAudit;
use App\Services\Async\AsyncTaskTracker;
use App\Services\Documents\DocumentScanService;
use App\Services\Documents\DocumentSecuritySettingsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScanBookingDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingDocumentId,
        public readonly int $taskRunId,
    ) {
    }

    public function handle(
        AsyncTaskTracker $tracker,
        DocumentScanService $scanService,
        ?DocumentSecuritySettingsService $settingsService = null
    ): void
    {
        $settingsService ??= app(DocumentSecuritySettingsService::class);
        $task = AsyncTaskRun::query()->find($this->taskRunId);
        $document = BookingDocument::withTrashed()->find($this->bookingDocumentId);
        if (! $task || ! $document) {
            return;
        }

        $tracker->markRunning($task);

        try {
            $document->update([
                'virus_scan_status' => 'pending_scan',
            ]);
            $absolutePath = Storage::disk($document->storage_disk)->path($document->storage_path);
            $result = $scanService->scanFile($absolutePath, [
                'booking_document_id' => $document->id,
                'booking_id' => $document->booking_id,
                'document_type' => $document->document_type,
                'mime_type' => $document->mime_type,
                'original_name' => $document->original_name,
            ]);
            $document->update([
                'virus_scan_status' => $result->status,
                'virus_scanned_at' => $result->checkedAt,
                'virus_scan_note' => $this->safeStatusMessage($result->status, $result->message),
            ]);

            $this->queueAutoRescanIfRequired($document->fresh(), $settingsService, $result->status);

            $tracker->markCompleted($task, [
                'virus_scan_status' => $result->status,
                'engine' => $result->engine,
                'signature' => $result->signature,
                'message' => $result->message,
                'duration_ms' => $result->durationMs,
                'checked_at' => $result->checkedAt->toIso8601String(),
                'meta' => $result->meta,
            ]);
        } catch (Throwable $e) {
            $document->update([
                'virus_scan_status' => 'failed',
                'virus_scanned_at' => now(),
                'virus_scan_note' => 'Scanner failed: '.$e->getMessage(),
            ]);
            $this->queueAutoRescanIfRequired($document->fresh(), $settingsService, 'failed');
            $tracker->markFailed($task, $e->getMessage());
        }
    }

    private function safeStatusMessage(string $status, ?string $message): string
    {
        return match ($status) {
            'clean' => 'File passed security scan.',
            'infected' => 'File blocked: malicious content detected.',
            'suspicious' => 'File blocked: suspicious content detected.',
            'skipped' => 'Scan skipped by environment configuration.',
            'failed' => 'Scan failed: file remains blocked until reviewed.',
            default => $message ?: 'Scan result unavailable.',
        };
    }

    private function queueAutoRescanIfRequired(
        BookingDocument $document,
        DocumentSecuritySettingsService $settingsService,
        string $scanStatus
    ): void {
        $policy = $settingsService->policy();
        $rescanPolicy = (string) ($policy['rescan_policy'] ?? 'manual_only');
        $maxAttempts = max(0, (int) ($policy['rescan_max_attempts'] ?? 1));
        if ($maxAttempts === 0 || ! $this->shouldAutoRescan($scanStatus, $rescanPolicy)) {
            return;
        }

        $alreadyQueued = BookingDocumentAudit::query()
            ->where('booking_document_id', $document->id)
            ->where('action', 'auto_rescan_queued')
            ->count();
        if ($alreadyQueued >= $maxAttempts) {
            return;
        }

        $task = AsyncTaskRun::query()->create([
            'task_type' => 'document_virus_scan',
            'status' => 'queued',
            'reference_type' => BookingDocument::class,
            'reference_id' => $document->id,
            'payload' => [
                'booking_document_id' => $document->id,
                'reason' => 'policy_auto_rescan',
                'policy' => $rescanPolicy,
                'attempt' => $alreadyQueued + 1,
                'max_attempts' => $maxAttempts,
            ],
        ]);
        BookingDocumentAudit::query()->create([
            'booking_document_id' => $document->id,
            'booking_id' => $document->booking_id,
            'action' => 'auto_rescan_queued',
            'actor_role' => 'system',
            'meta' => [
                'policy' => $rescanPolicy,
                'scan_status' => $scanStatus,
                'attempt' => $alreadyQueued + 1,
                'max_attempts' => $maxAttempts,
                'task_run_id' => $task->id,
            ],
            'created_at' => now(),
        ]);

        self::dispatch($document->id, $task->id);
    }

    private function shouldAutoRescan(string $scanStatus, string $policy): bool
    {
        return match ($policy) {
            'auto_on_failed' => $scanStatus === 'failed',
            'auto_on_failed_or_suspicious' => in_array($scanStatus, ['failed', 'suspicious'], true),
            default => false,
        };
    }
}
