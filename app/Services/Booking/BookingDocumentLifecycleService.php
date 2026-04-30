<?php

namespace App\Services\Booking;

use App\Jobs\ScanBookingDocumentJob;
use App\Models\AsyncTaskRun;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingDocumentAudit;
use App\Models\Customer;
use App\Models\User;
use App\Services\Async\AsyncTaskTracker;
use App\Services\Documents\DocumentScanStateEnforcer;
use App\Services\Documents\DocumentUploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BookingDocumentLifecycleService
{
    public function __construct(
        private readonly AsyncTaskTracker $taskTracker,
        private readonly DocumentUploadSecurityService $uploadSecurity,
        private readonly DocumentScanStateEnforcer $scanStateEnforcer,
    ) {
    }

    public function uploadByAdmin(
        Booking $booking,
        UploadedFile $file,
        string $documentType,
        bool $isCustomerVisible,
        ?string $notes,
        User $actor
    ): BookingDocument {
        return DB::transaction(function () use ($booking, $file, $documentType, $isCustomerVisible, $notes, $actor): BookingDocument {
            $latest = BookingDocument::withTrashed()
                ->where('booking_id', $booking->id)
                ->where('document_type', $documentType)
                ->orderByDesc('version_number')
                ->first();

            $groupUuid = $latest?->document_group_uuid ?? Str::uuid()->toString();
            $version = ($latest?->version_number ?? 0) + 1;
            $disk = 'local';
            $inspection = $this->uploadSecurity->inspect($file);
            if (in_array($inspection['category'], ['blocked', 'oversize', 'unsupported'], true)) {
                throw new RuntimeException('Upload did not pass security policy checks.');
            }
            $dir = $this->uploadSecurity->safeStorageDirectory($booking->id);
            $hash = hash_file('sha256', (string) $file->getRealPath());
            $name = $this->uploadSecurity->safeStorageFileName($inspection['extension']);
            $path = $file->storeAs($dir, $name, $disk);

            $doc = BookingDocument::query()->create([
                'booking_id' => $booking->id,
                'uploaded_by_user_id' => $actor->id,
                'document_type' => $documentType,
                'document_group_uuid' => $groupUuid,
                'version_number' => $version,
                'validation_status' => 'pending',
                'virus_scan_status' => 'pending_scan',
                'original_name' => $file->getClientOriginalName(),
                'storage_disk' => $disk,
                'storage_path' => $path,
                'mime_type' => $inspection['detected_mime'],
                'size_bytes' => $inspection['size_bytes'],
                'sha256' => $hash,
                'is_customer_visible' => $isCustomerVisible,
                'notes' => $notes,
            ]);

            if ($latest !== null) {
                $latest->update(['superseded_by_document_id' => $doc->id]);
            }

            $this->audit($doc, 'uploaded', $actor, null, [
                'document_type' => $documentType,
                'version_number' => $version,
                'sha256' => $hash,
            ]);

            $task = $this->taskTracker->create(
                taskType: 'document_virus_scan',
                reference: $doc,
                requestedByUserId: $actor->id,
                payload: ['booking_document_id' => $doc->id]
            );
            ScanBookingDocumentJob::dispatch($doc->id, $task->id);

            return $doc;
        });
    }

    public function validateByAdmin(
        BookingDocument $document,
        string $status,
        bool $isCustomerVisible,
        ?string $validationNote,
        User $actor
    ): void {
        if ($status === 'approved' && ! $this->scanStateEnforcer->canApprove($document)) {
            throw ValidationException::withMessages([
                'validation_status' => $this->scanStateEnforcer->blockedReason($document),
            ]);
        }

        $document->update([
            'validation_status' => $status,
            'is_customer_visible' => $isCustomerVisible,
            'validation_note' => $validationNote,
            'validated_by_user_id' => $actor->id,
            'validated_at' => $status === 'pending' ? null : now(),
        ]);

        $this->audit($document->fresh(), 'validated', $actor, null, [
            'validation_status' => $status,
            'is_customer_visible' => $isCustomerVisible,
        ]);
    }

    public function archiveByAdmin(BookingDocument $document, User $actor, ?string $note = null): void
    {
        $document->update([
            'archived_at' => now(),
            'archived_by_user_id' => $actor->id,
            'validation_note' => $note ?: $document->validation_note,
        ]);
        $document->delete(); // soft-delete

        $this->audit($document->fresh(), 'archived', $actor, null, [
            'note' => $note,
        ]);
    }

    public function recordDownloadByAdmin(BookingDocument $document, User $actor): void
    {
        $this->audit($document, 'downloaded', $actor, null, []);
    }

    public function recordBlockedDownloadByAdmin(BookingDocument $document, User $actor, string $reason): void
    {
        $this->audit($document, 'download_blocked', $actor, null, [
            'reason' => $reason,
            'virus_scan_status' => $document->virus_scan_status,
        ]);
    }

    public function recordDownloadByCustomer(BookingDocument $document, Customer $actor): void
    {
        $this->audit($document, 'downloaded', null, $actor, []);
    }

    public function recordBlockedDownloadByCustomer(BookingDocument $document, Customer $actor, string $reason): void
    {
        $this->audit($document, 'download_blocked', null, $actor, [
            'reason' => $reason,
            'virus_scan_status' => $document->virus_scan_status,
        ]);
    }

    public function requestRescanByAdmin(BookingDocument $document, User $actor): AsyncTaskRun
    {
        $document->update([
            'virus_scan_status' => BookingDocument::SCAN_PENDING,
            'virus_scan_note' => 'Rescan requested by admin.',
            'virus_scanned_at' => null,
        ]);

        $this->audit($document->fresh(), 'rescan_requested', $actor, null, [
            'virus_scan_status' => BookingDocument::SCAN_PENDING,
        ]);

        $task = $this->taskTracker->create(
            taskType: 'document_virus_scan',
            reference: $document,
            requestedByUserId: $actor->id,
            payload: ['booking_document_id' => $document->id, 'reason' => 'admin_rescan']
        );
        ScanBookingDocumentJob::dispatch($document->id, $task->id);

        return $task;
    }

    private function audit(
        BookingDocument $document,
        string $action,
        ?User $actorUser,
        ?Customer $actorCustomer,
        array $meta
    ): void {
        BookingDocumentAudit::query()->create([
            'booking_document_id' => $document->id,
            'booking_id' => $document->booking_id,
            'action' => $action,
            'actor_user_id' => $actorUser?->id,
            'actor_customer_id' => $actorCustomer?->id,
            'actor_role' => $actorUser ? 'admin' : ($actorCustomer ? 'customer' : null),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
