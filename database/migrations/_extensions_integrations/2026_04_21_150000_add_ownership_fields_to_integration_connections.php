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
            if (! Schema::hasColumn('integration_connections', 'ownership_type')) {
                $table->string('ownership_type', 32)->default('tenant')->after('tenant_id');
            }
            if (! Schema::hasColumn('integration_connections', 'ownership_tenant_id')) {
                $table->foreignId('ownership_tenant_id')->nullable()->after('ownership_type')->constrained('tenants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            if (Schema::hasColumn('integration_connections', 'ownership_tenant_id')) {
                $table->dropConstrainedForeignId('ownership_tenant_id');
            }
            if (Schema::hasColumn('integration_connections', 'ownership_type')) {
                $table->dropColumn('ownership_type');
            }
        });
    }
};
