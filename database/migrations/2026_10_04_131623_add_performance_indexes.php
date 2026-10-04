<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the dashboards filter and join on (customer, basket, date, status...).
 * Only adds missing indexes; no data is changed.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> table => index name => columns */
    private array $indexes = [
        'laundry_transactions' => [
            'lt_customer_id_index' => ['customer_id'],
            'lt_basket_tag_id_index' => ['basket_tag_id'],
            'lt_service_id_index' => ['service_id'],
            'lt_created_at_index' => ['created_at'],
            'lt_payment_created_index' => ['payment_status', 'created_at'],
        ],
        'finance_transactions' => [
            'ft_laundry_transaction_id_index' => ['laundry_transaction_id'],
            'ft_transaction_date_index' => ['transaction_date'],
        ],
        'inventory_restocks' => [
            'ir_restocked_at_index' => ['restocked_at'],
        ],
        'inventory_items' => [
            'ii_status_index' => ['status'],
        ],
        'notifications' => [
            'notif_type_read_target_index' => ['type', 'is_read', 'notifiable_type', 'notifiable_id'],
        ],
        'transaction_status_logs' => [
            'tsl_transaction_status_index' => ['laundry_transaction_id', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
