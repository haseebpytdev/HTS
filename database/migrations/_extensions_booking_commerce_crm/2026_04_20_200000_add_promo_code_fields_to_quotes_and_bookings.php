<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table): void {
                $table->foreignId('promo_code_id')->nullable()->after('discount_amount')->constrained('promo_codes')->nullOnDelete();
                $table->string('promo_code', 80)->nullable()->after('promo_code_id');
                $table->decimal('promo_discount_amount', 12, 2)->default(0)->after('promo_code');
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreignId('promo_code_id')->nullable()->after('tax_amount')->constrained('promo_codes')->nullOnDelete();
                $table->string('promo_code', 80)->nullable()->after('promo_code_id');
                $table->decimal('promo_discount_amount', 12, 2)->default(0)->after('promo_code');
                $table->decimal('discount_amount', 12, 2)->default(0)->after('promo_discount_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (Schema::hasColumn('bookings', 'promo_code_id')) {
                    $table->dropConstrainedForeignId('promo_code_id');
                }
                $drop = array_values(array_filter([
                    Schema::hasColumn('bookings', 'promo_code') ? 'promo_code' : null,
                    Schema::hasColumn('bookings', 'promo_discount_amount') ? 'promo_discount_amount' : null,
                    Schema::hasColumn('bookings', 'discount_amount') ? 'discount_amount' : null,
                ]));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table): void {
                if (Schema::hasColumn('quotations', 'promo_code_id')) {
                    $table->dropConstrainedForeignId('promo_code_id');
                }
                $drop = array_values(array_filter([
                    Schema::hasColumn('quotations', 'promo_code') ? 'promo_code' : null,
                    Schema::hasColumn('quotations', 'promo_discount_amount') ? 'promo_discount_amount' : null,
                ]));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
