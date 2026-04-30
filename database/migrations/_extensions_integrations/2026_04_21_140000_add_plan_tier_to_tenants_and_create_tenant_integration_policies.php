<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'plan_tier')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('plan_tier', 32)->default('basic')->after('slug');
            });
        }

        if (! Schema::hasTable('tenant_integration_policies')) {
            Schema::create('tenant_integration_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->json('allowed_providers')->nullable();
                $table->json('provider_permissions')->nullable();
                $table->boolean('allow_multi_provider')->default(false);
                $table->boolean('allow_fallback')->default(false);
                $table->json('provider_priority')->nullable();
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_integration_policies');

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'plan_tier')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropColumn('plan_tier');
            });
        }
    }
};
