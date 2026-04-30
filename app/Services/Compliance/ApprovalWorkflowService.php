<?php

namespace App\Services\Compliance;

use App\Models\ApprovalRequest;

class ApprovalWorkflowService
{
    /**
     * @return list<string>
     */
    public function supportedRequestTypes(): array
    {
        return [
            'payment_refund',
            'tenancy_toggle',
            'provider_production_activation',
            'provider_production_credential_replace',
            'tenant_plan_change',
            'manual_ledger_adjustment',
            'booking_force_cancel',
            'dangerous_setting_change',
            'document_security_override',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function submit(string $requestType, ?int $requestedBy, ?string $referenceType, ?int $referenceId, ?string $reason, array $payload = [], mixed $expiresAt = null): ApprovalRequest
    {
        $ttlHours = max(1, (int) config('compliance.approval_gate_ttl_hours', 72));
        $defaultExpiresAt = now()->addHours($ttlHours);
        $resolvedExpiresAt = $defaultExpiresAt;
        if ($expiresAt !== null) {
            try {
                $resolvedExpiresAt = \Illuminate\Support\Carbon::parse($expiresAt);
            } catch (\Throwable) {
                $resolvedExpiresAt = $defaultExpiresAt;
            }
        }

        if ($resolvedExpiresAt->lt(now()->addMinute())) {
            $resolvedExpiresAt = now()->addMinute();
        }

        return ApprovalRequest::query()->create([
            'request_type' => $requestType,
            'status' => 'pending',
            'requested_by_user_id' => $requestedBy,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_url' => isset($payload['reference_url']) && is_string($payload['reference_url']) ? $payload['reference_url'] : null,
            'reason' => $reason,
            'payload' => $payload ?: null,
            'submitted_at' => now(),
            'expires_at' => $resolvedExpiresAt,
        ]);
    }

    public function approve(ApprovalRequest $request, int $reviewerUserId, ?string $reviewNote): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            return $request->fresh() ?? $request;
        }

        $request->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $reviewerUserId,
            'review_note' => $reviewNote,
            'reviewed_at' => now(),
        ]);

        return $request->fresh();
    }

    public function reject(ApprovalRequest $request, int $reviewerUserId, ?string $reviewNote): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            return $request->fresh() ?? $request;
        }

        $request->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $reviewerUserId,
            'review_note' => $reviewNote,
            'reviewed_at' => now(),
        ]);

        return $request->fresh();
    }
}
