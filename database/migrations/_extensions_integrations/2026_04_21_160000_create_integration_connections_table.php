<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            Schema::create('integration_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->string('provider', 32);
                $table->string('environment', 32);
                $table->string('account_name')->nullable();
                $table->boolean('is_active')->default(false);
                $table->boolean('is_default')->default(false);
                $table->string('status', 32)->default('untested');
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->text('last_failure_reason')->nullable();
                $table->json('supported_operations')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                // Backward-compatible fields already used by existing services.
                $table->string('name')->nullable();
                $table->string('account_key')->nullable()->index();
                $table->foreignId('ownership_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->string('ownership_type', 32)->default('tenant');
                $table->text('base_url')->nullable();
                $table->json('config')->nullable();
                $table->string('last_tested_status', 32)->nullable();
                $table->timestamp('last_checked_at')->nullable();

                $table->timestamps();

                $table->index(['provider', 'environment']);
                $table->index(['tenant_id', 'provider', 'environment']);
            });

            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('integration_connections', 'account_name')) {
                $table->string('account_name')->nullable()->after('environment');
            }
            if (! Schema::hasColumn('integration_connections', 'status')) {
                $table->string('status', 32)->default('untested')->after('is_default');
            }
            if (! Schema::hasColumn('integration_connections', 'last_tested_at')) {
                $table->timestamp('last_tested_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('integration_connections', 'last_failure_at')) {
                $table->timestamp('last_failure_at')->nullable()->after('last_success_at');
            }
            if (! Schema::hasColumn('integration_connections', 'supported_operations')) {
                $table->json('supported_operations')->nullable()->after('last_failure_reason');
            }
            if (! Schema::hasColumn('integration_connections', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('supported_operations')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('integration_connections', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            if (Schema::hasColumn('integration_connections', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
            if (Schema::hasColumn('integration_connections', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            foreach (['supported_operations', 'last_failure_at', 'last_tested_at', 'status', 'account_name'] as $column) {
                if (Schema::hasColumn('integration_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
