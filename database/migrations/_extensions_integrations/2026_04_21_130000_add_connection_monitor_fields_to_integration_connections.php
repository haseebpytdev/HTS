<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('integration_connections', 'last_tested_status')) {
                $table->string('last_tested_status', 32)->nullable()->after('is_default');
            }
            if (! Schema::hasColumn('integration_connections', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('last_tested_status');
            }
            if (! Schema::hasColumn('integration_connections', 'last_success_at')) {
                $table->timestamp('last_success_at')->nullable()->after('last_checked_at');
            }
            if (! Schema::hasColumn('integration_connections', 'last_failure_reason')) {
                $table->text('last_failure_reason')->nullable()->after('last_success_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            foreach (['last_failure_reason', 'last_success_at', 'last_checked_at', 'last_tested_status'] as $col) {
                if (Schema::hasColumn('integration_connections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
