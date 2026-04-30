<?php

namespace App\Automation\Listeners;

use App\Automation\Events\InquiryFollowUpScheduled;
use App\Services\Automation\AutomationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerInquiryFollowUpScheduledAutomation implements ShouldQueue
{
    public function __construct(
        private readonly AutomationEngine $automationEngine
    ) {
    }

    public function handle(InquiryFollowUpScheduled $event): void
    {
        $this->automationEngine->dispatchFollowUpScheduled($event->followUp);
    }
}
