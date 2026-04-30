<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('plan_tier')->default('basic');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->string('role')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_logs')) {
            Schema::create('integration_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 40);
                $table->string('correlation_id', 80);
                $table->string('log_type', 80);
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('async_task_runs')) {
            Schema::create('async_task_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('task_type', 120);
                $table->string('status', 40)->default('queued');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->json('payload')->nullable();
                $table->json('result')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_search_sessions')) {
            Schema::create('supplier_search_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('correlation_id', 80)->unique();
                $table->string('provider', 40);
                $table->string('environment', 40)->default('production');
                $table->string('status', 40)->default('pending');
                $table->json('internal_request_snapshot')->nullable();
                $table->json('search_results_summary')->nullable();
                $table->unsignedBigInteger('integration_connection_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_offer_snapshots')) {
            Schema::create('supplier_offer_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('supplier_search_session_id');
                $table->string('offer_key', 120);
                $table->string('provider_offer_reference', 160)->nullable();
                $table->json('normalized_offer');
                $table->json('selected_fare_summary')->nullable();
                $table->boolean('is_selected')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_booking_snapshots')) {
            Schema::create('supplier_booking_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->string('correlation_id', 80);
                $table->unsignedBigInteger('integration_connection_id')->nullable();
                $table->unsignedBigInteger('integration_request_log_id')->nullable();
                $table->string('provider', 40);
                $table->string('booking_reference', 120)->nullable();
                $table->string('pnr', 80)->nullable();
                $table->string('internal_status', 40)->nullable();
                $table->json('normalized_totals')->nullable();
                $table->json('travelers_json')->nullable();
                $table->json('booking_summary')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_booking_snapshots');
        Schema::dropIfExists('supplier_offer_snapshots');
        Schema::dropIfExists('supplier_search_sessions');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('async_task_runs');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
