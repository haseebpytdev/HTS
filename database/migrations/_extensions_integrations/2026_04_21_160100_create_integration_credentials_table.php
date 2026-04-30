<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_credentials')) {
            Schema::create('integration_credentials', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('integration_connection_id')->constrained('integration_connections')->cascadeOnDelete();
                $table->string('credential_key', 64);
                $table->text('credential_value_encrypted');
                $table->boolean('is_secret')->default(true);
                $table->timestamps();

                // Backward-compatible aliases.
                $table->string('key_name', 64)->nullable();
                $table->text('secret')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->unique(['integration_connection_id', 'credential_key'], 'integration_credentials_unique_key');
            });

            return;
        }

        Schema::table('integration_credentials', function (Blueprint $table): void {
            if (! Schema::hasColumn('integration_credentials', 'credential_key')) {
                $table->string('credential_key', 64)->nullable()->after('integration_connection_id');
            }
            if (! Schema::hasColumn('integration_credentials', 'credential_value_encrypted')) {
                $table->text('credential_value_encrypted')->nullable()->after('credential_key');
            }
            if (! Schema::hasColumn('integration_credentials', 'is_secret')) {
                $table->boolean('is_secret')->default(true)->after('credential_value_encrypted');
            }
            if (! Schema::hasColumn('integration_credentials', 'key_name')) {
                $table->string('key_name', 64)->nullable()->after('is_secret');
            }
            if (! Schema::hasColumn('integration_credentials', 'secret')) {
                $table->text('secret')->nullable()->after('key_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_credentials')) {
            return;
        }

        Schema::table('integration_credentials', function (Blueprint $table): void {
            foreach (['is_secret', 'credential_value_encrypted', 'credential_key'] as $column) {
                if (Schema::hasColumn('integration_credentials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
