<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_restocks', function (Blueprint $table) {
            $table->timestamp('stocked_at')->nullable()->after('restocked_at');
        });

        // Receipts recorded before this change already went straight into inventory.
        DB::table('inventory_restocks')->update(['stocked_at' => DB::raw('created_at')]);

        // Plain-language unit names ("pc" is not clear to everyone).
        DB::table('inventory_items')->whereIn('unit', ['pc', 'pcs', 'Pc', 'Pcs'])->update(['unit' => 'piece']);
    }

    public function down(): void
    {
        Schema::table('inventory_restocks', function (Blueprint $table) {
            $table->dropColumn('stocked_at');
        });
    }
};
