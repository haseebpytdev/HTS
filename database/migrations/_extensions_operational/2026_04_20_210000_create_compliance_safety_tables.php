<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('area', 50);
            $table->string('action', 120);
            $table->string('entity_type', 120)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('severity', 20)->default('info');
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('correlation_id', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['area', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_type', 80);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->text('review_note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'request_type']);
        });

        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('backup_type', 40)->default('database');
            $table->string('disk', 40)->default('local');
            $table->string('path', 255)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('compliance_audit_logs');
    }
};
