<?php

namespace App\Automation\Jobs;

use App\Models\InquiryFollowUp;
use App\Services\Automation\AutomationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchInquiryFollowUpReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $followUpId
    ) {
    }

    public function handle(AutomationEngine $automationEngine): void
    {
        $followUp = InquiryFollowUp::query()->find($this->followUpId);
        if (! $followUp || ! $followUp->isOpen()) {
            return;
        }

        $automationEngine->dispatchFollowUpReminder($followUp);

        $followUp->forceFill([
            'reminder_sent_at' => now(),
        ])->save();
    }
}
