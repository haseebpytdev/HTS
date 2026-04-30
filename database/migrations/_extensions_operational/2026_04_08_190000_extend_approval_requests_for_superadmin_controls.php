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
                $table->foreignId('consumed_by_user_id')->nullable()->after('consumed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('approval_requests', 'reference_url')) {
                $table->string('reference_url', 2048)->nullable()->after('reference_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            $drop = [];
            foreach (['expires_at', 'consumed_at', 'consumed_by_user_id', 'reference_url'] as $col) {
                if (Schema::hasColumn('approval_requests', $col)) {
                    $drop[] = $col;
                }
            }
            if (in_array('consumed_by_user_id', $drop, true)) {
                $table->dropConstrainedForeignId('consumed_by_user_id');
                $drop = array_values(array_diff($drop, ['consumed_by_user_id']));
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
