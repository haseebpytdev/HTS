<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'supplier_cost_total')) {
                $table->decimal('supplier_cost_total', 14, 2)->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('bookings', 'supplier_cost_currency')) {
                $table->string('supplier_cost_currency', 3)->nullable()->after('supplier_cost_total');
            }
            if (! Schema::hasColumn('bookings', 'supplier_cost_recorded_at')) {
                $table->timestamp('supplier_cost_recorded_at')->nullable()->after('supplier_cost_currency');
            }
            if (! Schema::hasColumn('bookings', 'supplier_cost_recorded_by_user_id')) {
                $table->foreignId('supplier_cost_recorded_by_user_id')
                    ->nullable()
                    ->after('supplier_cost_recorded_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'supplier_cost_recorded_by_user_id')) {
                $table->dropForeign(['supplier_cost_recorded_by_user_id']);
            }
            foreach ([
                'supplier_cost_total',
                'supplier_cost_currency',
                'supplier_cost_recorded_at',
                'supplier_cost_recorded_by_user_id',
            ] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
