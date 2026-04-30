<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteInquiryFollowUpRequest;
use App\Http\Requests\Admin\LogInquiryCallRequest;
use App\Http\Requests\Admin\StoreInquiryFollowUpRequest;
use App\Http\Requests\Admin\StoreInquiryNoteRequest;
use App\Http\Requests\Admin\UpdateInquiryPipelineRequest;
use App\Enums\LeadPipelineStage;
use App\Models\Inquiry;
use App\Models\InquiryFollowUp;
use App\Services\Crm\InquiryCrmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class InquiryCrmController extends Controller
{
    public function __construct(
        private readonly InquiryCrmService $crm
    ) {}

    public function logCall(LogInquiryCallRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $data = $request->validated();
        $this->crm->logCall($inquiry, $data['summary'], $data['title'] ?? null, auth()->id());

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Call logged.');
    }

    public function storeNote(StoreInquiryNoteRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $this->crm->logNote($inquiry, $request->validated()['body'], auth()->id());

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Note added.');
    }

    public function updatePipeline(UpdateInquiryPipelineRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $data = $request->validated();
        if (array_key_exists('estimated_value', $data)) {
            $inquiry->update([
                'estimated_value' => $data['estimated_value'],
            ]);
        }

        $stage = $data['pipeline_stage'];
        if (! $stage instanceof LeadPipelineStage) {
            $stage = LeadPipelineStage::from((string) $stage);
        }

        $this->crm->changePipelineStage(
            $inquiry->fresh(),
            $stage,
            auth()->id()
        );

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Pipeline updated.');
    }

    public function storeFollowUp(StoreInquiryFollowUpRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $data = $request->validated();
        $this->crm->scheduleFollowUp(
            $inquiry,
            $data['title'],
            $data['description'] ?? null,
            Carbon::parse($data['due_at']),
            $data['assigned_to'] ?? null,
            auth()->id()
        );

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Follow-up scheduled.');
    }

    public function completeFollowUp(
        CompleteInquiryFollowUpRequest $request,
        Inquiry $inquiry,
        InquiryFollowUp $followUp
    ): RedirectResponse {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $this->crm->completeFollowUp($followUp, $request->validated()['note'] ?? null, auth()->id());

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Follow-up marked complete.');
    }
}
