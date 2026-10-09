<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_bills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('payee')->nullable();
            $table->string('category', 20)->default('utilities');
            $table->decimal('usual_amount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bill_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_bill_id')->constrained()->restrictOnDelete();
            $table->string('period_month', 7);
            $table->decimal('amount_due', 10, 2)->nullable();
            $table->date('due_date');
            $table->timestamps();
            $table->unique(['recurring_bill_id', 'period_month']);
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('description');
            $table->foreignId('bill_statement_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->string('reference_no', 80)->nullable()->after('bill_statement_id');
        });

        // Existing stock-receipt expenses belong to the "stock" category.
        DB::table('finance_transactions')->whereNotNull('inventory_restock_id')->update(['category' => 'stock']);

        $now = now();
        foreach ([
            ['Davao Light (electricity)', 'Davao Light', 'utilities', null],
            ['Water bill', 'Water district', 'utilities', null],
            ['Commercial space rent', null, 'rent', 12305],
            ['BIR tax on rent', 'BIR', 'tax', 575],
        ] as [$name, $payee, $category, $amount]) {
            DB::table('recurring_bills')->insert([
                'name' => $name, 'payee' => $payee, 'category' => $category, 'usual_amount' => $amount,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // LPG is bought as stock (Stock Receiving), not as a bill.
        $categoryId = DB::table('inventory_categories')->where('name', 'Supply')->value('id')
            ?? DB::table('inventory_categories')->insertGetId(['name' => 'Supply', 'created_at' => $now, 'updated_at' => $now]);
        if (! DB::table('inventory_items')->where('name', 'LPG 50kg tank')->exists()) {
            DB::table('inventory_items')->insert([
                'inventory_category_id' => $categoryId, 'name' => 'LPG 50kg tank', 'unit' => 'tank',
                'unit_price' => 4550, 'quantity_on_hand' => 0, 'low_stock_threshold' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_statement_id');
            $table->dropColumn(['category', 'reference_no']);
        });
        Schema::dropIfExists('bill_statements');
        Schema::dropIfExists('recurring_bills');
    }
};
