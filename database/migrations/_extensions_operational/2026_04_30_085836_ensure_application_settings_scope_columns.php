<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_settings')) {
            return;
        }

        Schema::table('application_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_settings', 'scope')) {
                $table->string('scope', 32)->default('platform')->after('id');
            }
            if (! Schema::hasColumn('application_settings', 'scope_id')) {
                $table->unsignedBigInteger('scope_id')->nullable()->after('scope');
            }
            if (! Schema::hasColumn('application_settings', 'provider')) {
                $table->string('provider', 64)->nullable()->after('scope_id');
            }
            if (! Schema::hasColumn('application_settings', 'module')) {
                $table->string('module', 64)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('application_settings', 'category')) {
                $table->string('category', 64)->nullable()->after('module');
            }
            if (! Schema::hasColumn('application_settings', 'value_type')) {
                $table->string('value_type', 16)->default('string')->after('value');
            }
        });

        DB::table('application_settings')
            ->whereNull('scope')
            ->update(['scope' => 'platform']);

        DB::table('application_settings')
            ->whereNull('value_type')
            ->update(['value_type' => 'string']);
    }

    public function down(): void
    {
        // No-op: this migration is a safety backfill for inconsistent historical ordering.
    }
};
