<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_modules', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_modules', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('service_modules', 'code')) {
                $table->string('code', 120)->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('service_modules', 'name')) {
                $table->string('name')->nullable()->after('code');
            }
            if (! Schema::hasColumn('service_modules', 'provider')) {
                $table->string('provider', 80)->nullable()->after('service_type');
            }
            if (! Schema::hasColumn('service_modules', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('service_modules', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('service_modules', 'status')) {
                $table->string('status', 40)->default('inactive')->after('is_default');
            }
            if (! Schema::hasColumn('service_modules', 'connection_status')) {
                $table->string('connection_status', 40)->default('disconnected')->after('status');
            }
            if (! Schema::hasColumn('service_modules', 'last_tested_at')) {
                $table->timestamp('last_tested_at')->nullable()->after('connection_status');
            }
            if (! Schema::hasColumn('service_modules', 'last_success_at')) {
                $table->timestamp('last_success_at')->nullable()->after('last_tested_at');
            }
            if (! Schema::hasColumn('service_modules', 'last_failure_at')) {
                $table->timestamp('last_failure_at')->nullable()->after('last_success_at');
            }
            if (! Schema::hasColumn('service_modules', 'last_failure_reason')) {
                $table->text('last_failure_reason')->nullable()->after('last_failure_at');
            }
            if (! Schema::hasColumn('service_modules', 'supported_operations_json')) {
                $table->json('supported_operations_json')->nullable()->after('last_failure_reason');
            }
            if (! Schema::hasColumn('service_modules', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(100)->after('supported_operations_json');
            }
        });

        DB::table('service_modules')->orderBy('id')->get()->each(function ($row): void {
            DB::table('service_modules')->where('id', $row->id)->update([
                'code' => $row->code ?: ($row->module_key ?? 'module_'.$row->id),
                'name' => $row->name ?: ($row->provider_name ?? 'Module '.$row->id),
                'provider' => $row->provider ?: ($row->provider_code ?? 'internal'),
                'logo_path' => $row->logo_path ?: ($row->provider_logo_url ?? null),
                'is_default' => (bool) ($row->is_default ?? $row->is_default_provider ?? false),
                'status' => $row->status ?: ((bool) ($row->is_active ?? false) ? 'active' : 'inactive'),
                'connection_status' => $row->connection_status ?: 'disconnected',
                'supported_operations_json' => $row->supported_operations_json ?: ($row->available_operations ?? null),
            ]);
        });

        Schema::create('service_module_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->string('credential_key', 80);
            $table->text('credential_value_encrypted');
            $table->boolean('is_secret')->default(true);
            $table->timestamps();
            $table->unique(['service_module_id', 'credential_key'], 'service_module_credentials_unique_idx');
        });

        Schema::create('service_module_tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->enum('tax_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('tax_value', 12, 2)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->timestamps();
        });

        Schema::create('service_module_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->enum('markup_type_b2b', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('markup_value_b2b', 12, 2)->default(0);
            $table->enum('markup_type_b2c', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('markup_value_b2c', 12, 2)->default(0);
            $table->string('base_currency_code', 3)->default('PKR');
            $table->timestamps();
        });

        Schema::create('service_module_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->string('result_status', 40)->default('warning');
            $table->timestamp('checked_at');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('message')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_module_health_checks');
        Schema::dropIfExists('service_module_pricing_rules');
        Schema::dropIfExists('service_module_tax_rules');
        Schema::dropIfExists('service_module_credentials');
    }
};
