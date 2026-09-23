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
        Schema::table('laundry_transactions', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('status');
            $table->timestamp('sms_sent_at')->nullable()->after('email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laundry_transactions', function (Blueprint $table) {
            $table->dropColumn(['email_sent_at', 'sms_sent_at']);
        });
    }
};
