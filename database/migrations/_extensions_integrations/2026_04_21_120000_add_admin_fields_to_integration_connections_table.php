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
            if (! Schema::hasColumn('integration_connections', 'account_key')) {
                $table->string('account_key', 64)->nullable()->after('id');
            }
            if (! Schema::hasColumn('integration_connections', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('name')->constrained('tenants')->nullOnDelete();
            }
            if (! Schema::hasColumn('integration_connections', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
        });

        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->index(['account_key']);
            $table->index(['tenant_id', 'environment']);
            $table->index(['tenant_id', 'environment', 'is_default'], 'integration_connections_tenant_env_default_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->dropIndex(['account_key']);
            $table->dropIndex(['tenant_id', 'environment']);
            $table->dropIndex('integration_connections_tenant_env_default_index');

            if (Schema::hasColumn('integration_connections', 'is_default')) {
                $table->dropColumn('is_default');
            }
            if (Schema::hasColumn('integration_connections', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
            if (Schema::hasColumn('integration_connections', 'account_key')) {
                $table->dropColumn('account_key');
            }
        });
    }
};
