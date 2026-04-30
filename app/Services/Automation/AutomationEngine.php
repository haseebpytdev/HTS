<?php

namespace App\Services\Automation;

use App\Contracts\Automation\AutomationProviderInterface;
use App\Models\Booking;
use App\Models\InquiryFollowUp;

class AutomationEngine
{
    public function __construct(
        private readonly AutomationProviderInterface $provider
    ) {
    }

    public function dispatchFollowUpReminder(InquiryFollowUp $followUp): void
    {
        $this->provider->sendReminder('inquiry.followup.reminder', [
            'follow_up_id' => $followUp->id,
            'inquiry_id' => $followUp->inquiry_id,
            'title' => $followUp->title,
            'due_at' => optional($followUp->due_at)?->toIso8601String(),
            'assigned_to' => $followUp->assigned_to,
            'correlation_id' => (string) str()->uuid(),
        ]);
    }

    public function dispatchBookingConfirmed(Booking $booking): void
    {
        $this->provider->triggerEvent('booking.confirmed', [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'agency_id' => $booking->agency_id,
            'correlation_id' => (string) str()->uuid(),
        ]);
    }

    public function dispatchFollowUpScheduled(InquiryFollowUp $followUp): void
    {
        $this->provider->triggerEvent('inquiry.followup.scheduled', [
            'follow_up_id' => $followUp->id,
            'inquiry_id' => $followUp->inquiry_id,
            'title' => $followUp->title,
            'due_at' => optional($followUp->due_at)?->toIso8601String(),
            'assigned_to' => $followUp->assigned_to,
            'correlation_id' => (string) str()->uuid(),
        ]);
    }
}
