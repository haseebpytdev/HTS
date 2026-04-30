<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_module_access')) {
            return;
        }

        Schema::create('tenant_module_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module_code', 120);
            $table->boolean('is_enabled')->default(false);
            $table->json('allowed_operations')->nullable();
            $table->json('usage_quota_json')->nullable();
            $table->unsignedTinyInteger('soft_limit_percent')->default(80);
            $table->boolean('hard_limit_enforced')->default(false);
            $table->boolean('overage_alert_enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'module_code'], 'tenant_module_access_unique');
            $table->index(['tenant_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_access');
    }
};
