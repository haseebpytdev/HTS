<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $after = Schema::hasColumn('tenants', 'plan_tier') ? 'plan_tier' : 'slug';

            if (! Schema::hasColumn('tenants', 'usage_quota_json')) {
                $table->json('usage_quota_json')->nullable()->after($after);
            }
            if (! Schema::hasColumn('tenants', 'soft_limit_percent')) {
                $table->unsignedTinyInteger('soft_limit_percent')->default(80)->after('usage_quota_json');
            }
            if (! Schema::hasColumn('tenants', 'hard_limit_enforced')) {
                $table->boolean('hard_limit_enforced')->default(false)->after('soft_limit_percent');
            }
            if (! Schema::hasColumn('tenants', 'overage_alert_enabled')) {
                $table->boolean('overage_alert_enabled')->default(true)->after('hard_limit_enforced');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $drop = [];
            foreach (['usage_quota_json', 'soft_limit_percent', 'hard_limit_enforced', 'overage_alert_enabled'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
