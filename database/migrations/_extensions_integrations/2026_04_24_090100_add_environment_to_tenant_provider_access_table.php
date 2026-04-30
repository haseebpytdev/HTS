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
            if (! Schema::hasColumn('tenant_provider_access', 'environment')) {
                $table->string('environment', 16)->default('production')->after('provider');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_provider_access')) {
            return;
        }

        Schema::table('tenant_provider_access', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_provider_access', 'environment')) {
                $table->dropColumn('environment');
            }
        });
    }
};
