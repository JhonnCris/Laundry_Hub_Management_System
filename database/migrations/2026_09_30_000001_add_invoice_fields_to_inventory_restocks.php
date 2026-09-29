<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_restocks', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_restocks', 'invoice_number')) {
                $table->string('invoice_number', 80)->nullable()->after('supplier');
            }
            if (! Schema::hasColumn('inventory_restocks', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('inventory_restocks', 'quantity_invoiced')) {
                $table->unsignedInteger('quantity_invoiced')->nullable()->after('quantity_received');
            }
            if (! Schema::hasColumn('inventory_restocks', 'cost')) {
                $table->decimal('cost', 12, 2)->nullable()->after('quantity_invoiced');
            }
            if (! Schema::hasColumn('inventory_restocks', 'notes')) {
                $table->string('notes', 255)->nullable()->after('cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_restocks', function (Blueprint $table) {
            foreach (['invoice_number', 'invoice_date', 'quantity_invoiced', 'cost', 'notes'] as $col) {
                if (Schema::hasColumn('inventory_restocks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
