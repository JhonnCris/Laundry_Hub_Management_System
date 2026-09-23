<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('laundry_transactions', 'detergent_quantity')) {
            Schema::table('laundry_transactions', function (Blueprint $table) {
                $table->unsignedSmallInteger('detergent_quantity')->default(0)->after('detergent_item_id');
            });
        }

        if (! Schema::hasColumn('laundry_transactions', 'cash_tendered')) {
            Schema::table('laundry_transactions', function (Blueprint $table) {
                $table->decimal('cash_tendered', 10, 2)->nullable()->after('total_amount');
            });
        }

        if (! Schema::hasColumn('laundry_transactions', 'change_given')) {
            Schema::table('laundry_transactions', function (Blueprint $table) {
                $table->decimal('change_given', 10, 2)->nullable()->after('cash_tendered');
            });
        }

        if (! Schema::hasColumn('laundry_transactions', 'load_weight_kg')) {
            Schema::table('laundry_transactions', function (Blueprint $table) {
                $table->decimal('load_weight_kg', 8, 2)->nullable()->after('notes');
            });
        }

        if (! Schema::hasColumn('laundry_transactions', 'cycle_minutes')) {
            Schema::table('laundry_transactions', function (Blueprint $table) {
                $table->unsignedSmallInteger('cycle_minutes')->nullable()->after('load_weight_kg');
            });
        }
    }

    public function down(): void
    {
        Schema::table('laundry_transactions', function (Blueprint $table) {
            $cols = [];
            foreach (['detergent_quantity', 'cash_tendered', 'change_given', 'load_weight_kg', 'cycle_minutes'] as $col) {
                if (Schema::hasColumn('laundry_transactions', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
