<?php

namespace App\Automation\Events;

use App\Models\InquiryFollowUp;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InquiryFollowUpScheduled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly InquiryFollowUp $followUp
    ) {
    }
}
