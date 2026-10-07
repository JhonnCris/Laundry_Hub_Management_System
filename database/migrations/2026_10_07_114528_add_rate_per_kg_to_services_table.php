<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('rate_per_kg', 8, 2)->nullable();
        });

        // The per-kg self-service rates that used to be fixed in the code.
        foreach (['Wash with Dry' => 40, 'Wash Only' => 25, 'Dry Only' => 20] as $name => $rate) {
            DB::table('services')->where('name', $name)->update(['rate_per_kg' => $rate]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('rate_per_kg');
        });
    }
};
