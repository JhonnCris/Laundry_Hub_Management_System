<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_number')->nullable();
            $table->timestamps();
            $table->index('name');
        });
        Schema::create('basket_tags', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('status', ['available', 'in_use', 'lost', 'retired'])->default('available');
            $table->timestamps();
        });
        Schema::create('garment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('base_price', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['washer', 'dryer']);
            $table->enum('status', ['available', 'in_use', 'reserved', 'maintenance', 'out_of_service'])->default('available');
            $table->timestamps();
        });
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('unit');
            $table->decimal('unit_price', 8, 2)->default(0);
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->timestamps();
            $table->index('name');
        });
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['staff', 'super_admin'])->default('staff');
            $table->timestamps();
        });
        Schema::create('machine_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_booked')->default(false);
            $table->timestamps();
            $table->unique(['machine_id', 'slot_date', 'start_time']);
        });
        Schema::create('laundry_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('basket_tag_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_time_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('detergent_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->enum('transaction_type', ['drop_off', 'self_service']);
            $table->enum('status', ['pending', 'processing', 'ready_for_pickup', 'claimed', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->decimal('subtotal', 8, 2)->default(0);
            $table->decimal('total_amount', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('transaction_garments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['laundry_transaction_id', 'garment_type_id'], 'transaction_garments_transaction_garment_unique');
        });
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('line_total', 8, 2);
            $table->timestamps();
            $table->unique(['laundry_transaction_id', 'inventory_item_id'], 'transaction_items_transaction_item_unique');
        });
        Schema::create('transaction_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('changed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
        });
        Schema::create('transaction_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('claimed_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->integer('quantity_received');
            $table->string('supplier')->nullable();
            $table->date('restocked_at');
            $table->timestamps();
        });
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->integer('quantity_change');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense']);
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('laundry_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
            $table->index(['type', 'transaction_date']);
        });
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in');
            $table->timestamp('clock_out')->nullable();
            $table->date('work_date');
            $table->timestamps();
            $table->index(['staff_id', 'work_date']);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['low_stock', 'out_of_stock', 'laundry_ready', 'unpaid_transaction', 'machine_maintenance']);
            $table->string('message');
            $table->nullableMorphs('notifiable');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('staff_attendance');
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_restocks');
        Schema::dropIfExists('transaction_claims');
        Schema::dropIfExists('transaction_status_logs');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transaction_garments');
        Schema::dropIfExists('laundry_transactions');
        Schema::dropIfExists('machine_time_slots');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('services');
        Schema::dropIfExists('garment_types');
        Schema::dropIfExists('basket_tags');
        Schema::dropIfExists('customers');
    }
};
