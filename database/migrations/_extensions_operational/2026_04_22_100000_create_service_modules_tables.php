<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('module_key')->unique();
            $table->string('provider_code', 50)->nullable();
            $table->string('provider_name');
            $table->string('provider_logo_url')->nullable();
            $table->string('service_type', 50);
            $table->enum('environment', ['sandbox', 'production'])->default('sandbox');
            $table->boolean('is_active')->default(false);
            $table->string('documentation_url')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->index(['service_type', 'is_active']);
        });

        Schema::create('service_module_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->enum('b2b_markup_type', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('b2b_markup_value', 12, 2)->default(0);
            $table->enum('b2c_markup_type', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('b2c_markup_value', 12, 2)->default(0);
            $table->string('base_currency', 3)->default('PKR');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('service_module_id');
        });

        Schema::create('module_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->enum('channel', ['b2b', 'b2c']);
            $table->enum('markup_type', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('markup_value', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['service_module_id', 'channel', 'is_active'], 'module_pricing_rules_module_channel_active_idx');
        });

        Schema::create('module_tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->string('rule_name')->default('default');
            $table->decimal('tax_percent', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['service_module_id', 'is_active']);
        });

        Schema::create('module_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->enum('connection_status', ['connected', 'disconnected', 'degraded'])->default('disconnected');
            $table->enum('health_status', ['healthy', 'warning', 'critical'])->default('warning');
            $table->unsignedTinyInteger('health_score')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['service_module_id', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_health_checks');
        Schema::dropIfExists('module_tax_rules');
        Schema::dropIfExists('module_pricing_rules');
        Schema::dropIfExists('service_module_settings');
        Schema::dropIfExists('service_modules');
    }
};
