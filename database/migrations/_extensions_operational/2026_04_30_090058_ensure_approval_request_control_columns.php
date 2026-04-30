<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_requests', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('approval_requests', 'consumed_at')) {
                $table->timestamp('consumed_at')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('approval_requests', 'consumed_by_user_id')) {
                $table->unsignedBigInteger('consumed_by_user_id')->nullable()->after('consumed_at');
            }
            if (! Schema::hasColumn('approval_requests', 'reference_url')) {
                $table->string('reference_url', 2048)->nullable()->after('reference_id');
            }
        });
    }

    public function down(): void
    {
        // No-op: this migration only backfills missing columns from historical migration order.
    }
};
