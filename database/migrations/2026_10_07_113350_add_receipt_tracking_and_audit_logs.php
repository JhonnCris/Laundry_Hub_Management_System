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
            $table->unsignedInteger('stocked_quantity')->default(0)->after('quantity_received');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
        });
        DB::table('inventory_restocks')->whereNotNull('stocked_at')->update(['stocked_quantity' => DB::raw('quantity_received')]);

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_restock_id')->nullable()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('rejection_reason')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email')->nullable();
            $table->string('action', 80)->index();
            $table->json('context')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('rejection_reason'));
        Schema::table('finance_transactions', fn (Blueprint $t) => $t->dropColumn('inventory_restock_id'));
        Schema::table('inventory_restocks', fn (Blueprint $t) => $t->dropColumn(['stocked_quantity', 'voided_at', 'void_reason']));
    }
};
