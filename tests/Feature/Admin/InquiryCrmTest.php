<?php

namespace Tests\Feature\Admin;

use App\Enums\InquiryActivityType;
use App\Enums\LeadPipelineStage;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryFollowUp;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryCrmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function makeInquiry(): Inquiry
    {
        return Inquiry::query()->create([
            'source' => 'quote',
            'name' => 'CRM Lead',
            'email' => 'crm-lead@example.com',
            'adults' => 2,
            'children' => 0,
            'currency' => 'PKR',
            'status' => Inquiry::STATUS_NEW,
            'pipeline_stage' => LeadPipelineStage::New->value,
        ]);
    }

    public function test_admin_can_log_note_and_advances_timeline(): void
    {
        $admin = $this->admin();
        $inquiry = $this->makeInquiry();

        $this->actingAs($admin)
            ->post(route('admin.inquiries.crm.notes', $inquiry), ['body' => 'Discussed budget.'])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'type' => InquiryActivityType::Note->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_pipeline_and_logs_change(): void
    {
        $admin = $this->admin();
        $inquiry = $this->makeInquiry();

        $this->actingAs($admin)
            ->patch(route('admin.inquiries.crm.pipeline', $inquiry), [
                'pipeline_stage' => LeadPipelineStage::Quoted->value,
                'estimated_value' => '150000.50',
            ])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        $inquiry->refresh();
        $this->assertSame(LeadPipelineStage::Quoted, $inquiry->pipeline_stage);
        $this->assertSame('150000.50', (string) $inquiry->estimated_value);

        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'type' => InquiryActivityType::PipelineChange->value,
        ]);
    }

    public function test_log_call_sets_last_contacted_at(): void
    {
        $admin = $this->admin();
        $inquiry = $this->makeInquiry();

        $this->actingAs($admin)
            ->post(route('admin.inquiries.crm.calls', $inquiry), [
                'summary' => 'Left voicemail.',
                'title' => 'Outbound',
            ])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        $inquiry->refresh();
        $this->assertNotNull($inquiry->last_contacted_at);
        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'type' => InquiryActivityType::Call->value,
        ]);
    }

    public function test_follow_up_lifecycle(): void
    {
        $admin = $this->admin();
        $inquiry = $this->makeInquiry();

        $due = now()->addDay()->seconds(0);

        $this->actingAs($admin)
            ->post(route('admin.inquiries.crm.follow-ups.store', $inquiry), [
                'title' => 'Call back',
                'due_at' => $due->toDateTimeString(),
                'assigned_to' => $admin->id,
                'description' => 'Confirm dates',
            ])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        $followUp = InquiryFollowUp::query()->where('inquiry_id', $inquiry->id)->firstOrFail();
        $this->assertTrue($followUp->isOpen());

        $this->actingAs($admin)
            ->patch(route('admin.inquiries.crm.follow-ups.complete', [$inquiry, $followUp]), [
                'note' => 'Customer confirmed.',
            ])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        $followUp->refresh();
        $this->assertNotNull($followUp->completed_at);
        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'type' => InquiryActivityType::FollowUpCompleted->value,
        ]);
    }

    public function test_status_update_records_activity(): void
    {
        $admin = $this->admin();
        $inquiry = $this->makeInquiry();

        $this->actingAs($admin)
            ->patch(route('admin.inquiries.update-status', $inquiry), [
                'status' => Inquiry::STATUS_CONTACTED,
                'admin_notes' => 'Called once.',
            ])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'type' => InquiryActivityType::StatusChange->value,
        ]);
    }

    public function test_inquiry_index_filters_by_pipeline_stage(): void
    {
        $admin = $this->admin();
        $a = $this->makeInquiry();
        $a->update(['email' => 'a@pipe.test', 'pipeline_stage' => LeadPipelineStage::New->value]);

        $b = Inquiry::query()->create([
            'source' => 'quote',
            'name' => 'Pipe B',
            'email' => 'b@pipe.test',
            'adults' => 1,
            'children' => 0,
            'currency' => 'PKR',
            'status' => Inquiry::STATUS_NEW,
            'pipeline_stage' => LeadPipelineStage::Quoted->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.inquiries.index', ['pipeline_stage' => LeadPipelineStage::Quoted->value]))
            ->assertOk()
            ->assertSee('b@pipe.test')
            ->assertDontSee('a@pipe.test');
    }
}
