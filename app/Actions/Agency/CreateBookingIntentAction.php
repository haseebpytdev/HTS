<?php

namespace App\Actions\Agency;

use App\Models\BookingIntent;
use App\Models\Inquiry;
use App\Models\Quotation;

class CreateBookingIntentAction
{
    public function execute(Quotation $quotation, int $agencyId, int $userId, ?string $note): BookingIntent
    {
        $intent = BookingIntent::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agencyId,
            'user_id' => $userId,
            'note' => $note,
            'status' => 'interested',
            'requested_at' => now(),
        ]);

        if ($quotation->inquiry_id) {
            Inquiry::query()
                ->whereKey($quotation->inquiry_id)
                ->update(['status' => Inquiry::STATUS_CONFIRMED]);
        }

        return $intent;
    }
}
