<?php

namespace App\Actions\Admin;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Services\SupportDesk\SupportDeskSlaService;

class CreateSupportTicketAction
{
    public function __construct(
        private readonly SupportDeskSlaService $slaService
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, ?int $createdByUserId): SupportTicket
    {
        $priority = SupportTicketPriority::from((string) ($attributes['priority'] ?? SupportTicketPriority::Medium->value));
        $slaDue = $this->slaService->dueDates($priority);

        return SupportTicket::query()->create([
            'ticket_number' => $this->nextTicketNumber(),
            'subject' => (string) $attributes['subject'],
            'description' => (string) $attributes['description'],
            'priority' => $priority->value,
            'status' => SupportTicketStatus::Open->value,
            'channel' => (string) ($attributes['channel'] ?? 'portal'),
            'agency_id' => $attributes['agency_id'] ?? null,
            'customer_id' => $attributes['customer_id'] ?? null,
            'assigned_to_user_id' => $attributes['assigned_to_user_id'] ?? null,
            'created_by_user_id' => $createdByUserId,
            'first_response_due_at' => $slaDue['first_response_due_at'],
            'resolution_due_at' => $slaDue['resolution_due_at'],
        ]);
    }

    private function nextTicketNumber(): string
    {
        $next = (int) SupportTicket::query()->count() + 1;

        return 'TKT-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
