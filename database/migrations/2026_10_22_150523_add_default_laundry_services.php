<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('services')->insertOrIgnore([
            [
                'name' => 'Wash Only',
                'base_price' => 70,
                'rate_per_kg' => 25,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Dry Only',
                'base_price' => 60,
                'rate_per_kg' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Wash with Dry',
                'base_price' => 120,
                'rate_per_kg' => 40,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve service data so rollback cannot remove configured or used services.
    }
};
