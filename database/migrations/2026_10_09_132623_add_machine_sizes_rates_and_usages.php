<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->string('size', 10)->default('giant')->after('type');
        });

        Schema::create('machine_rates', function (Blueprint $table) {
            $table->id();
            $table->string('size', 10);
            $table->string('kind', 10);
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->decimal('price', 8, 2);
            $table->decimal('capacity_kg', 5, 1)->nullable();
            $table->timestamps();
            $table->unique(['size', 'kind', 'minutes']);
        });

        Schema::create('transaction_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->decimal('price', 8, 2);
            $table->timestamps();
            $table->unique(['laundry_transaction_id', 'machine_id']);
        });

        $now = now();
        $rows = [
            ['giant', 'wash', 0, 65, 8],
            ['titan', 'wash', 0, 110, 11.5],
            ['giant', 'dry', 20, 50, null],
            ['giant', 'dry', 30, 75, null],
            ['giant', 'dry', 40, 100, null],
            ['titan', 'dry', 20, 60, null],
            ['titan', 'dry', 30, 90, null],
            ['titan', 'dry', 40, 120, null],
        ];
        DB::table('machine_rates')->insert(array_map(fn ($r) => [
            'size' => $r[0], 'kind' => $r[1], 'minutes' => $r[2], 'price' => $r[3], 'capacity_kg' => $r[4],
            'created_at' => $now, 'updated_at' => $now,
        ], $rows));

        // Orders that used a single machine before this change keep showing it.
        DB::table('laundry_transactions')->whereNotNull('machine_id')->orderBy('id')->each(function ($t) use ($now) {
            DB::table('transaction_machines')->insert([
                'laundry_transaction_id' => $t->id, 'machine_id' => $t->machine_id,
                'minutes' => (int) ($t->cycle_minutes ?? 0), 'price' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_machines');
        Schema::dropIfExists('machine_rates');
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};
