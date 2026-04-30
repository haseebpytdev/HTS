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
            if (! Schema::hasColumn('integration_connections', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('last_checked_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        Schema::table('integration_connections', function (Blueprint $table): void {
            if (Schema::hasColumn('integration_connections', 'token_expires_at')) {
                $table->dropColumn('token_expires_at');
            }
        });
    }
};
