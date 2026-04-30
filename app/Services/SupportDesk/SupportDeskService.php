<?php

namespace App\Services\SupportDesk;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Jobs\Communication\SendEmailMessageJob;
use App\Jobs\Communication\SendInAppNotificationJob;
use App\Jobs\Communication\SendSmsMessageJob;
use App\Jobs\Communication\SendWhatsappMessageJob;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;

class SupportDeskService
{
    public function addReply(SupportTicket $ticket, string $message, ?int $userId, bool $isInternal = false): SupportTicketReply
    {
        $reply = $ticket->replies()->create([
            'user_id' => $userId,
            'message' => $message,
            'is_internal' => $isInternal,
        ]);

        if ($ticket->first_responded_at === null) {
            $ticket->forceFill(['first_responded_at' => now()])->save();
        }

        if ($ticket->status === SupportTicketStatus::Open) {
            $ticket->forceFill(['status' => SupportTicketStatus::InProgress->value])->save();
        }

        if (! $isInternal) {
            $this->notifyOnReply($ticket->fresh(['customer', 'assignee', 'creator', 'escalatedTo']), $reply);
        }

        return $reply;
    }

    public function resolve(SupportTicket $ticket, ?string $resolutionNote, ?int $userId): void
    {
        if ($resolutionNote !== null && trim($resolutionNote) !== '') {
            $this->addReply($ticket, $resolutionNote, $userId, true);
        }

        $ticket->forceFill([
            'status' => SupportTicketStatus::Resolved->value,
            'resolved_at' => now(),
        ])->save();
    }

    public function escalate(SupportTicket $ticket, int $toUserId, string $reason): void
    {
        $ticket->forceFill([
            'escalated_to_user_id' => $toUserId,
            'assigned_to_user_id' => $toUserId,
            'escalated_at' => now(),
            'escalation_reason' => $reason,
            'status' => SupportTicketStatus::InProgress->value,
        ])->save();

        $this->notifyOnEscalation($ticket->fresh(['customer', 'assignee', 'creator', 'escalatedTo']), $reason);
    }

    private function notifyOnReply(SupportTicket $ticket, SupportTicketReply $reply): void
    {
        $subject = sprintf('[%s] Support update: %s', $ticket->ticket_number, $ticket->subject);
        $body = "A new reply was added to your support ticket.\n\n".$reply->message;
        $meta = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'event' => 'support.reply',
            'reply_id' => $reply->id,
            'priority' => $this->priorityValue($ticket),
            'channel' => $ticket->channel,
        ];

        $this->dispatchByChannelAndPriority(
            channel: (string) $ticket->channel,
            priority: $this->priorityValue($ticket),
            email: $ticket->customer?->email,
            phone: $ticket->customer?->phone,
            subject: $subject,
            body: $body,
            meta: $meta
        );

        if ($ticket->assigned_to_user_id !== null) {
            SendInAppNotificationJob::dispatch(
                userId: (int) $ticket->assigned_to_user_id,
                email: null,
                phone: null,
                subject: $subject,
                body: 'A new reply was posted on your assigned support ticket.',
                meta: $meta
            );
        }
    }

    private function notifyOnEscalation(SupportTicket $ticket, string $reason): void
    {
        $subject = sprintf('[%s] Ticket escalated', $ticket->ticket_number);
        $body = "Your support ticket has been escalated.\n\nReason: ".$reason;
        $meta = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'event' => 'support.escalated',
            'priority' => $this->priorityValue($ticket),
            'channel' => $ticket->channel,
        ];

        $this->dispatchByChannelAndPriority(
            channel: (string) $ticket->channel,
            priority: $this->priorityValue($ticket),
            email: $ticket->customer?->email,
            phone: $ticket->customer?->phone,
            subject: $subject,
            body: $body,
            meta: $meta
        );

        if ($ticket->assigned_to_user_id !== null) {
            SendInAppNotificationJob::dispatch(
                userId: (int) $ticket->assigned_to_user_id,
                email: null,
                phone: null,
                subject: $subject,
                body: 'A support ticket has been escalated to you.',
                meta: $meta + ['escalation_reason' => $reason]
            );
        }
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function dispatchByChannelAndPriority(
        string $channel,
        string $priority,
        ?string $email,
        ?string $phone,
        string $subject,
        string $body,
        array $meta
    ): void {
        match ($channel) {
            'whatsapp' => SendWhatsappMessageJob::dispatch($email, $phone, $subject, $body, $meta),
            'sms' => SendSmsMessageJob::dispatch($email, $phone, $subject, $body, $meta),
            'email', 'portal' => SendEmailMessageJob::dispatch($email, $phone, $subject, $body, $meta),
            default => SendEmailMessageJob::dispatch($email, $phone, $subject, $body, $meta),
        };

        if (in_array($priority, [SupportTicketPriority::High->value, SupportTicketPriority::Urgent->value], true)) {
            SendWhatsappMessageJob::dispatch($email, $phone, $subject, $body, $meta + ['route' => 'priority_whatsapp']);
            SendSmsMessageJob::dispatch($email, $phone, $subject, $body, $meta + ['route' => 'priority_sms']);
        }
    }

    private function priorityValue(SupportTicket $ticket): string
    {
        return $ticket->priority instanceof SupportTicketPriority
            ? $ticket->priority->value
            : (string) $ticket->priority;
    }
}
