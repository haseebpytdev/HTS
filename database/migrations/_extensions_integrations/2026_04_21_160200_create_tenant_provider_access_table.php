<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_provider_access')) {
            return;
        }

        Schema::create('tenant_provider_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('plan_code', 64)->nullable();
            $table->boolean('can_search')->default(false);
            $table->boolean('can_price')->default(false);
            $table->boolean('can_book')->default(false);
            $table->boolean('allow_multi_provider')->default(false);
            $table->boolean('allow_fallback')->default(false);
            $table->unsignedInteger('priority_order')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'provider'], 'tenant_provider_access_unique');
            $table->index(['tenant_id', 'is_enabled', 'priority_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provider_access');
    }
};
