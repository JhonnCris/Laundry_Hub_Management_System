<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe to re-run: an earlier attempt may have added a narrower column before stopping.
        if (Schema::hasColumn('customers', 'phone_key')) {
            Schema::table('customers', fn (Blueprint $table) => $table->string('phone_key', 40)->nullable()->change());
        } else {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('phone_key', 40)->nullable()->index();
            });
        }

        // One normalised form per number (0917…, +63 917… and 63917… all match).
        DB::table('customers')->select('id', 'contact_number')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('customers')->where('id', $row->id)->update(['phone_key' => Customer::phoneKey($row->contact_number)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('phone_key');
        });
    }
};
