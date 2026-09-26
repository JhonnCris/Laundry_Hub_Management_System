<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('customers', function (Blueprint $table) {
        if (! Schema::hasColumn('customers', 'email')) {
            $table->string('email')->nullable()->after('contact_number');
        }
        if (! Schema::hasColumn('customers', 'address')) {
            $table->string('address')->nullable()->after('email');
        }
    });
}

public function down(): void
{
    Schema::table('customers', function (Blueprint $table) {
        if (Schema::hasColumn('customers', 'email')) {
            $table->dropColumn('email');
        }
        if (Schema::hasColumn('customers', 'address')) {
            $table->dropColumn('address');
        }
    });
}
};
