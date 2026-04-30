<?php

namespace Tests\Feature\Support;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserRole;
use App\Jobs\Communication\SendEmailMessageJob;
use App\Jobs\Communication\SendInAppNotificationJob;
use App\Jobs\Communication\SendSmsMessageJob;
use App\Jobs\Communication\SendWhatsappMessageJob;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupportTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_ticket_with_priority_and_sla_dates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $assignee = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::query()->create(['name' => 'Support Agency', 'code' => 'SUP-'.uniqid(), 'is_active' => true]);
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->post(route('admin.support-tickets.store'), [
            'subject' => 'Ticket creation test',
            'description' => 'Customer cannot download invoice from portal.',
            'priority' => SupportTicketPriority::Urgent->value,
            'channel' => 'portal',
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'assigned_to_user_id' => $assignee->id,
        ])->assertRedirect();

        $ticket = SupportTicket::query()->latest('id')->firstOrFail();
        $this->assertSame(SupportTicketStatus::Open, $ticket->status);
        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
    }

    public function test_public_reply_triggers_communication_and_updates_status(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $assignee = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $customer = Customer::factory()->create();
        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'TKT-TEST-1',
            'subject' => 'Reply trigger',
            'description' => 'Need status update.',
            'priority' => SupportTicketPriority::High->value,
            'status' => SupportTicketStatus::Open->value,
            'channel' => 'email',
            'customer_id' => $customer->id,
            'assigned_to_user_id' => $assignee->id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.support-tickets.reply', $ticket), [
            'message' => 'We have started investigating your issue.',
            'is_internal' => false,
        ])->assertRedirect(route('admin.support-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertNotNull($ticket->first_responded_at);
        $this->assertSame(1, $ticket->replies()->count());

        Queue::assertPushed(SendEmailMessageJob::class);
        Queue::assertPushed(SendInAppNotificationJob::class);
        Queue::assertPushed(SendWhatsappMessageJob::class);
        Queue::assertPushed(SendSmsMessageJob::class);
    }

    public function test_internal_reply_does_not_trigger_customer_communication_jobs(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'TKT-TEST-2',
            'subject' => 'Internal note',
            'description' => 'Internal workflow',
            'priority' => SupportTicketPriority::Medium->value,
            'status' => SupportTicketStatus::Open->value,
            'channel' => 'portal',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.support-tickets.reply', $ticket), [
            'message' => 'Internal-only follow-up note.',
            'is_internal' => true,
        ])->assertRedirect(route('admin.support-tickets.show', $ticket));

        Queue::assertNotPushed(SendEmailMessageJob::class);
        Queue::assertNotPushed(SendWhatsappMessageJob::class);
        Queue::assertNotPushed(SendSmsMessageJob::class);
    }

    public function test_escalation_reassigns_ticket_and_dispatches_notifications(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $escalatedTo = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $customer = Customer::factory()->create();
        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'TKT-TEST-3',
            'subject' => 'Escalation',
            'description' => 'Escalation required',
            'priority' => SupportTicketPriority::Urgent->value,
            'status' => SupportTicketStatus::Open->value,
            'channel' => 'whatsapp',
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.support-tickets.escalate', $ticket), [
            'to_user_id' => $escalatedTo->id,
            'reason' => 'Requires urgent supplier coordination',
        ])->assertRedirect(route('admin.support-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame($escalatedTo->id, $ticket->assigned_to_user_id);
        $this->assertSame($escalatedTo->id, $ticket->escalated_to_user_id);
        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertNotNull($ticket->escalated_at);

        Queue::assertPushed(SendInAppNotificationJob::class);
        Queue::assertPushed(SendWhatsappMessageJob::class);
        Queue::assertPushed(SendSmsMessageJob::class);
    }
}
