<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the customer's name into atomic parts. The existing `name` column stays (it is the
 * display name everywhere) and is rebuilt from the parts whenever they are saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One column per statement: TiDB cannot place a column "after" another one added in the same ALTER.
        Schema::table('customers', fn (Blueprint $table) => $table->string('first_name', 80)->nullable()->after('id'));
        Schema::table('customers', fn (Blueprint $table) => $table->string('middle_name', 80)->nullable()->after('first_name'));
        Schema::table('customers', fn (Blueprint $table) => $table->string('last_name', 80)->nullable()->after('middle_name'));
        Schema::table('customers', fn (Blueprint $table) => $table->index('last_name', 'customers_last_name_index'));

        // Best-effort split of existing names: first word = first name, last word = last name,
        // anything in between = middle name. The display `name` is left exactly as it was.
        DB::table('customers')->orderBy('id')->each(function ($customer) {
            $parts = preg_split('/\s+/', trim((string) $customer->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($parts === []) {
                return;
            }

            $first = array_shift($parts);
            $last = $parts ? array_pop($parts) : null;

            DB::table('customers')->where('id', $customer->id)->update([
                'first_name' => $first,
                'middle_name' => $parts ? implode(' ', $parts) : null,
                'last_name' => $last,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_last_name_index');
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
