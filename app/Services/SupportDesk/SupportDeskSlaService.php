<?php

namespace App\Services\SupportDesk;

use App\Enums\SupportTicketPriority;
use Illuminate\Support\Carbon;

class SupportDeskSlaService
{
    /**
     * @return array{first_response_due_at: Carbon, resolution_due_at: Carbon}
     */
    public function dueDates(SupportTicketPriority $priority, ?Carbon $openedAt = null): array
    {
        $openedAt ??= now();
        $key = $priority->value;
        $policy = config("support_desk.sla_hours.{$key}", [
            'first_response' => 8,
            'resolution' => 24,
        ]);

        $firstResponseHours = max(1, (int) ($policy['first_response'] ?? 8));
        $resolutionHours = max($firstResponseHours, (int) ($policy['resolution'] ?? 24));

        return [
            'first_response_due_at' => $openedAt->copy()->addHours($firstResponseHours),
            'resolution_due_at' => $openedAt->copy()->addHours($resolutionHours),
        ];
    }
}
