<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_items', 'status')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->string('status')->default('active')->after('low_stock_threshold');
                // active | archived_expired | archived_spoiled | archived_damaged
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_items', 'status')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
