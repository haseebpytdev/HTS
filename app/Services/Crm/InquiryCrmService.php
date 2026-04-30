<?php

namespace App\Services\Crm;

use App\Automation\Events\InquiryFollowUpScheduled;
use App\Enums\InquiryActivityType;
use App\Enums\LeadPipelineStage;
use App\Models\Inquiry;
use App\Models\InquiryActivity;
use App\Models\InquiryFollowUp;
use Illuminate\Support\Carbon;

class InquiryCrmService
{
    public function logCall(Inquiry $inquiry, string $summary, ?string $title, ?int $userId): InquiryActivity
    {
        $inquiry->update(['last_contacted_at' => now()]);

        return $this->createActivity(
            $inquiry,
            InquiryActivityType::Call,
            $title ?? 'Call',
            $summary,
            null,
            $userId
        );
    }

    public function logNote(Inquiry $inquiry, string $body, ?int $userId): InquiryActivity
    {
        return $this->createActivity(
            $inquiry,
            InquiryActivityType::Note,
            'Note',
            $body,
            null,
            $userId
        );
    }

    public function recordStatusChange(Inquiry $inquiry, ?string $from, string $to, ?int $userId): InquiryActivity
    {
        return $this->createActivity(
            $inquiry,
            InquiryActivityType::StatusChange,
            'Status updated',
            null,
            ['from' => $from, 'to' => $to],
            $userId
        );
    }

    public function changePipelineStage(Inquiry $inquiry, LeadPipelineStage $stage, ?int $userId): void
    {
        $from = $inquiry->pipeline_stage instanceof LeadPipelineStage
            ? $inquiry->pipeline_stage->value
            : (string) $inquiry->pipeline_stage;

        if ($from === $stage->value) {
            return;
        }

        $inquiry->update(['pipeline_stage' => $stage->value]);

        $this->createActivity(
            $inquiry,
            InquiryActivityType::PipelineChange,
            'Pipeline stage',
            null,
            ['from' => $from, 'to' => $stage->value],
            $userId
        );
    }

    public function markConverted(Inquiry $inquiry, string $message, ?int $userId): void
    {
        $this->changePipelineStage($inquiry, LeadPipelineStage::Converted, $userId);
        $this->logNote($inquiry, $message, $userId);
    }

    public function scheduleFollowUp(
        Inquiry $inquiry,
        string $title,
        ?string $description,
        Carbon $dueAt,
        ?int $assignedTo,
        ?int $createdBy
    ): InquiryFollowUp {
        $followUp = InquiryFollowUp::query()->create([
            'inquiry_id' => $inquiry->id,
            'created_by' => $createdBy,
            'assigned_to' => $assignedTo,
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
        ]);

        InquiryFollowUpScheduled::dispatch($followUp);

        return $followUp;
    }

    public function completeFollowUp(InquiryFollowUp $followUp, ?string $note, ?int $userId): void
    {
        if ($followUp->completed_at !== null) {
            return;
        }

        $followUp->update(['completed_at' => now()]);

        $this->createActivity(
            $followUp->inquiry,
            InquiryActivityType::FollowUpCompleted,
            'Follow-up completed: '.$followUp->title,
            $note,
            ['follow_up_id' => $followUp->id],
            $userId
        );
    }

    private function createActivity(
        Inquiry $inquiry,
        InquiryActivityType $type,
        ?string $title,
        ?string $body,
        ?array $metadata,
        ?int $userId
    ): InquiryActivity {
        return InquiryActivity::query()->create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
