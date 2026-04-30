<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_modules', function (Blueprint $table): void {
            $table->boolean('is_default_provider')->default(false)->after('is_active');
            $table->json('available_operations')->nullable()->after('is_default_provider');
            $table->string('environment', 20)->default('sandbox')->change();
        });

        Schema::create('module_provider_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_module_id')->constrained('service_modules')->cascadeOnDelete();
            $table->enum('environment', ['sandbox', 'production']);
            $table->string('credential_key', 80);
            $table->text('credential_value_encrypted');
            $table->boolean('is_secret')->default(true);
            $table->timestamps();
            $table->unique(['service_module_id', 'environment', 'credential_key'], 'module_provider_credentials_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_provider_credentials');

        Schema::table('service_modules', function (Blueprint $table): void {
            $table->dropColumn(['is_default_provider', 'available_operations']);
            $table->enum('environment', ['sandbox', 'production'])->default('sandbox')->change();
        });
    }
};
