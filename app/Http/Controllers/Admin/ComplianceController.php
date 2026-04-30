<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewApprovalRequest;
use App\Http\Requests\Admin\StoreApprovalRequest;
use App\Models\ApprovalRequest;
use App\Models\BackupRun;
use App\Models\ComplianceAuditLog;
use App\Services\Compliance\ApiMonitoringService;
use App\Services\Compliance\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ComplianceController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $approvalWorkflowService,
        private readonly ApiMonitoringService $apiMonitoringService
    ) {
    }

    public function dashboard(): View
    {
        $this->authorize('access-admin-area');

        $pendingApprovals = ApprovalRequest::query()
            ->where('status', 'pending')
            ->latest('id')
            ->limit(20)
            ->get();
        $approvalHistory = ApprovalRequest::query()
            ->whereIn('status', ['approved', 'rejected', 'consumed'])
            ->latest('id')
            ->limit(40)
            ->get();

        return view('admin.compliance.dashboard', [
            'auditLogs' => ComplianceAuditLog::query()->latest('id')->limit(20)->get(),
            'pendingApprovals' => $pendingApprovals,
            'approvalHistory' => $approvalHistory,
            'supportedApprovalTypes' => $this->approvalWorkflowService->supportedRequestTypes(),
            'apiMonitoring' => $this->apiMonitoringService->summary(),
            'backupRuns' => BackupRun::query()->latest('id')->limit(20)->get(),
        ]);
    }

    public function storeApproval(StoreApprovalRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $referenceType = $this->normalizeReferenceType($request->validated('reference_type'));

        $this->approvalWorkflowService->submit(
            requestType: (string) $request->validated('request_type'),
            requestedBy: auth()->id(),
            referenceType: $referenceType,
            referenceId: $request->validated('reference_id'),
            reason: $request->validated('reason'),
            payload: (array) ($request->validated('payload') ?? []),
            expiresAt: $request->validated('expires_at')
        );

        return redirect()->route('admin.compliance.dashboard')->with('success', 'Approval request submitted.');
    }

    public function reviewApproval(ReviewApprovalRequest $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $this->authorize('access-admin-area');

        if ($request->validated('decision') === 'approve') {
            $this->approvalWorkflowService->approve($approvalRequest, (int) auth()->id(), $request->validated('review_note'));
        } else {
            $this->approvalWorkflowService->reject($approvalRequest, (int) auth()->id(), $request->validated('review_note'));
        }

        return redirect()->route('admin.compliance.dashboard')->with('success', 'Approval request reviewed.');
    }

    private function normalizeReferenceType(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            return null;
        }

        return match (mb_strtolower($value)) {
            'payment' => \App\Models\Payment::class,
            'booking' => \App\Models\Booking::class,
            'quotation' => \App\Models\Quotation::class,
            'tenant' => \App\Models\Tenant::class,
            'module', 'service_module' => \App\Models\ServiceModule::class,
            'integration', 'integration_connection' => \App\Models\IntegrationConnection::class,
            'agency' => \App\Models\Agency::class,
            default => $value,
        };
    }
}
