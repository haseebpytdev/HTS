<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadPipelineStage;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryFollowUp;
use App\Models\Quotation;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InquiryOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureInquiryOpsTablesExist();
    }

    private function ensureInquiryOpsTablesExist(): void
    {
        if (! Schema::hasTable('agencies')) {
            Schema::create('agencies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('destination_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('title');
                $table->string('slug')->unique();
                $table->decimal('base_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('PKR');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('destinations')) {
            Schema::create('destinations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inquiries')) {
            Schema::create('inquiries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('source')->default('frontend');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('destination_id')->nullable();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->date('travel_date')->nullable();
                $table->unsignedTinyInteger('adults')->default(1);
                $table->unsignedTinyInteger('children')->default(0);
                $table->string('currency', 3)->default('PKR');
                $table->text('message')->nullable();
                $table->text('admin_notes')->nullable();
                $table->string('status')->default('new');
                $table->string('pipeline_stage', 32)->default('new');
                $table->decimal('estimated_value', 12, 2)->nullable();
                $table->timestamp('last_contacted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('inquiry_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('quote_number')->nullable();
                $table->string('customer_name');
                $table->string('currency', 3)->default('PKR');
                $table->unsignedTinyInteger('adults')->default(1);
                $table->unsignedTinyInteger('children')->default(0);
                $table->unsignedTinyInteger('infants')->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('status')->default('draft');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inquiry_follow_ups')) {
            Schema::create('inquiry_follow_ups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('inquiry_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('due_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inquiry_activities')) {
            Schema::create('inquiry_activities', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('inquiry_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type');
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }
    }

    public function test_index_supports_advanced_operations_filters_and_export_query_consistency(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $assignee = User::factory()->create(['role' => UserRole::ADMIN->value, 'name' => 'Assigned User']);
        $agency = Agency::query()->create(['name' => 'Ops Agency', 'code' => 'OPS-'.uniqid(), 'is_active' => true]);
        $package = TravelPackage::query()->create([
            'agency_id' => $agency->id,
            'title' => 'Ops Package',
            'slug' => 'ops-package-'.uniqid(),
            'base_price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $keep = Inquiry::query()->create([
            'source' => 'package',
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'assigned_to' => $assignee->id,
            'name' => 'Keep Lead',
            'email' => 'keep@example.com',
            'adults' => 2,
            'children' => 0,
            'currency' => 'PKR',
            'status' => Inquiry::STATUS_QUOTED,
            'pipeline_stage' => LeadPipelineStage::Quoted->value,
            'created_at' => now()->subDay(),
        ]);
        $drop = Inquiry::query()->create([
            'source' => 'group',
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'name' => 'Drop Lead',
            'email' => 'drop@example.com',
            'adults' => 1,
            'children' => 0,
            'currency' => 'PKR',
            'status' => Inquiry::STATUS_NEW,
            'pipeline_stage' => LeadPipelineStage::New->value,
            'created_at' => now()->subDays(10),
        ]);

        Quotation::query()->create([
            'inquiry_id' => $keep->id,
            'agency_id' => $agency->id,
            'user_id' => $admin->id,
            'quote_number' => 'Q-OPS-'.uniqid(),
            'customer_name' => 'Keep Lead',
            'currency' => 'PKR',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'draft',
        ]);

        InquiryFollowUp::query()->create([
            'inquiry_id' => $keep->id,
            'title' => 'Call back',
            'due_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inquiries.index', [
            'assigned_to' => $assignee->id,
            'has_quotation' => '1',
            'has_open_follow_up' => '1',
            'created_from' => now()->subDays(2)->toDateString(),
            'created_to' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('Keep Lead')
            ->assertDontSee('Drop Lead')
            ->assertSee('/admin/exports/inquiries.csv?assigned_to='.$assignee->id, false)
            ->assertSee('has_open_follow_up=1', false);
    }

    public function test_show_page_displays_assignment_notes_helpers_and_conversion_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $assignee = User::factory()->create(['role' => UserRole::ADMIN->value, 'name' => 'Ops Agent']);
        $agency = Agency::query()->create(['name' => 'Ops Agency B', 'code' => 'OPB-'.uniqid(), 'is_active' => true]);

        $inquiry = Inquiry::query()->create([
            'source' => 'quote',
            'agency_id' => $agency->id,
            'assigned_to' => $assignee->id,
            'name' => 'Ops Inquiry',
            'email' => 'ops@example.com',
            'adults' => 1,
            'children' => 0,
            'currency' => 'PKR',
            'status' => Inquiry::STATUS_CONTACTED,
            'pipeline_stage' => LeadPipelineStage::Contacted->value,
        ]);

        Quotation::query()->create([
            'inquiry_id' => $inquiry->id,
            'agency_id' => $agency->id,
            'user_id' => $admin->id,
            'quote_number' => 'Q-CONV-'.uniqid(),
            'customer_name' => 'Ops Inquiry',
            'currency' => 'PKR',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Assigned:')
            ->assertSee('Ops Agent')
            ->assertSee('Awaiting response')
            ->assertSee('Convert to Booking Intent');
    }
}
