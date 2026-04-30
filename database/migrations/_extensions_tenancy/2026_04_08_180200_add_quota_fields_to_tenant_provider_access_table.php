<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_provider_access')) {
            return;
        }

        Schema::table('tenant_provider_access', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_provider_access', 'usage_quota_json')) {
                $table->json('usage_quota_json')->nullable()->after('is_enabled');
            }
            if (! Schema::hasColumn('tenant_provider_access', 'soft_limit_percent')) {
                $table->unsignedTinyInteger('soft_limit_percent')->default(80)->after('usage_quota_json');
            }
            if (! Schema::hasColumn('tenant_provider_access', 'hard_limit_enforced')) {
                $table->boolean('hard_limit_enforced')->default(false)->after('soft_limit_percent');
            }
            if (! Schema::hasColumn('tenant_provider_access', 'overage_alert_enabled')) {
                $table->boolean('overage_alert_enabled')->default(true)->after('hard_limit_enforced');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_provider_access')) {
            return;
        }

        Schema::table('tenant_provider_access', function (Blueprint $table): void {
            $drop = [];
            foreach (['usage_quota_json', 'soft_limit_percent', 'hard_limit_enforced', 'overage_alert_enabled'] as $col) {
                if (Schema::hasColumn('tenant_provider_access', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
