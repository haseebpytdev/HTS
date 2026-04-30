<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_modules')) {
            return;
        }

        Schema::table('service_modules', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_modules', 'provider_priority')) {
                $table->unsignedInteger('provider_priority')->default(100)->after('sort_order');
            }
            if (! Schema::hasColumn('service_modules', 'allow_fallback')) {
                $table->boolean('allow_fallback')->default(true)->after('provider_priority');
            }
            if (! Schema::hasColumn('service_modules', 'allow_multi_provider')) {
                $table->boolean('allow_multi_provider')->default(false)->after('allow_fallback');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_modules')) {
            return;
        }

        Schema::table('service_modules', function (Blueprint $table): void {
            $drop = [];
            foreach (['provider_priority', 'allow_fallback', 'allow_multi_provider'] as $column) {
                if (Schema::hasColumn('service_modules', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
