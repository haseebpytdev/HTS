<?php

namespace App\Automation\Listeners;

use App\Automation\Events\BookingConfirmed;
use App\Services\Automation\AutomationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerBookingConfirmedAutomation implements ShouldQueue
{
    public function __construct(
        private readonly AutomationEngine $automationEngine
    ) {
    }

    public function handle(BookingConfirmed $event): void
    {
        $this->automationEngine->dispatchBookingConfirmed($event->booking);
    }
}
