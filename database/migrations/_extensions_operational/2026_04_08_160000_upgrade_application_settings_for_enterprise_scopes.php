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
            if (Schema::hasColumn('application_settings', 'key')) {
                $table->dropUnique('application_settings_key_unique');
            }

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
            ->select(['id', 'key', 'value'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $key = (string) $row->key;
                    $value = $row->value;
                    $parts = explode('.', trim($key), 2);
                    $category = trim((string) ($parts[0] ?? '')) ?: null;

                    $valueType = 'string';
                    if ($value !== null) {
                        $raw = trim((string) $value);
                        if ($raw === '0' || $raw === '1' || strcasecmp($raw, 'true') === 0 || strcasecmp($raw, 'false') === 0) {
                            $valueType = 'bool';
                        } elseif (is_numeric($raw)) {
                            $valueType = str_contains($raw, '.') ? 'float' : 'int';
                        } elseif ((str_starts_with($raw, '{') && str_ends_with($raw, '}')) || (str_starts_with($raw, '[') && str_ends_with($raw, ']'))) {
                            $valueType = 'json';
                        }
                    }

                    DB::table('application_settings')
                        ->where('id', $row->id)
                        ->update([
                            'scope' => 'platform',
                            'scope_id' => null,
                            'provider' => null,
                            'module' => null,
                            'category' => $category,
                            'value_type' => $valueType,
                        ]);
                }
            });

        Schema::table('application_settings', function (Blueprint $table): void {
            $table->unique(
                ['scope', 'scope_id', 'provider', 'module', 'category', 'key'],
                'application_settings_scope_unique'
            );
            $table->index(['scope', 'scope_id', 'category'], 'application_settings_scope_category_index');
            $table->index(['provider', 'module'], 'application_settings_provider_module_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_settings')) {
            return;
        }

        Schema::table('application_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('application_settings', 'scope')) {
                $table->dropUnique('application_settings_scope_unique');
                $table->dropIndex('application_settings_scope_category_index');
                $table->dropIndex('application_settings_provider_module_index');
                $table->dropColumn(['scope', 'scope_id', 'provider', 'module', 'category', 'value_type']);
            }

            $table->unique('key');
        });
    }
};
