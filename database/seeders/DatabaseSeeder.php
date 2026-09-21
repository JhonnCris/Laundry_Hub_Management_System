<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        User::factory()->create(['name' => 'SSK Staff', 'email' => 'staff@ssklabadami.test', 'password' => 'password']);
        $staffId = DB::table('staff')->insertGetId(['name' => 'SSK Staff', 'email' => 'staff@ssklabadami.test', 'password' => Hash::make('password'), 'role' => 'super_admin', 'created_at' => $now, 'updated_at' => $now]);
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana Cruz', 'contact_number' => '09171234567', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['name' => 'John Reyes', 'contact_number' => '09181234567', 'created_at' => $now, 'updated_at' => $now]);
        foreach (range(1, 20) as $number) {
            DB::table('basket_tags')->insert(['code' => '#'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'status' => $number === 14 ? 'in_use' : 'available', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('garment_types')->insert(collect(['Shirt', 'Shorts', 'Pants', 'Beddings'])->map(fn (string $name) => ['name' => $name, 'is_custom' => false, 'created_at' => $now, 'updated_at' => $now])->all());
        DB::table('services')->insert(collect([['Wash Only', 70], ['Dry Only', 60], ['Wash with Dry', 120]])->map(fn (array $service) => ['name' => $service[0], 'base_price' => $service[1], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])->all());
        DB::table('machines')->insert([['name' => 'Washer 1', 'type' => 'washer', 'status' => 'in_use', 'created_at' => $now, 'updated_at' => $now], ['name' => 'Washer 2', 'type' => 'washer', 'status' => 'available', 'created_at' => $now, 'updated_at' => $now], ['name' => 'Dryer 1', 'type' => 'dryer', 'status' => 'available', 'created_at' => $now, 'updated_at' => $now]]);
        DB::table('inventory_categories')->insert(collect(['Detergent', 'Consumable', 'Snack/Drink', 'Supply'])->map(fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])->all());
        $categories = DB::table('inventory_categories')->pluck('id', 'name');
        DB::table('inventory_items')->insert([['inventory_category_id' => $categories['Detergent'], 'name' => 'Ariel Sachet', 'unit' => 'sachet', 'unit_price' => 12, 'quantity_on_hand' => 25, 'low_stock_threshold' => 5, 'created_at' => $now, 'updated_at' => $now], ['inventory_category_id' => $categories['Consumable'], 'name' => 'Downy Sachet', 'unit' => 'sachet', 'unit_price' => 10, 'quantity_on_hand' => 4, 'low_stock_threshold' => 5, 'created_at' => $now, 'updated_at' => $now], ['inventory_category_id' => $categories['Snack/Drink'], 'name' => 'Softdrinks', 'unit' => 'bottle', 'unit_price' => 30, 'quantity_on_hand' => 40, 'low_stock_threshold' => 10, 'created_at' => $now, 'updated_at' => $now], ['inventory_category_id' => $categories['Snack/Drink'], 'name' => 'Water Big', 'unit' => 'bottle', 'unit_price' => 20, 'quantity_on_hand' => 30, 'low_stock_threshold' => 10, 'created_at' => $now, 'updated_at' => $now]]);
        $machineId = DB::table('machines')->where('name', 'Washer 1')->value('id');
        $slotId = DB::table('machine_time_slots')->insertGetId(['machine_id' => $machineId, 'slot_date' => today(), 'start_time' => '09:00', 'end_time' => '10:00', 'is_booked' => true, 'created_at' => $now, 'updated_at' => $now]);
        $transactionId = DB::table('laundry_transactions')->insertGetId(['customer_id' => $customerId, 'basket_tag_id' => DB::table('basket_tags')->where('code', '#014')->value('id'), 'machine_id' => $machineId, 'machine_time_slot_id' => $slotId, 'service_id' => DB::table('services')->where('name', 'Wash with Dry')->value('id'), 'detergent_item_id' => DB::table('inventory_items')->where('name', 'Downy Sachet')->value('id'), 'handled_by' => $staffId, 'transaction_type' => 'drop_off', 'status' => 'processing', 'payment_status' => 'paid', 'subtotal' => 120, 'total_amount' => 150, 'notes' => 'Separate light-colored garments.', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('transaction_garments')->insert(['laundry_transaction_id' => $transactionId, 'garment_type_id' => DB::table('garment_types')->where('name', 'Shirt')->value('id'), 'quantity' => 14, 'created_at' => $now, 'updated_at' => $now]);
        $drinkId = DB::table('inventory_items')->where('name', 'Softdrinks')->value('id');
        DB::table('transaction_items')->insert(['laundry_transaction_id' => $transactionId, 'inventory_item_id' => $drinkId, 'quantity' => 1, 'unit_price' => 30, 'line_total' => 30, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('transaction_status_logs')->insert(['laundry_transaction_id' => $transactionId, 'status' => 'processing', 'changed_by' => $staffId, 'changed_at' => $now]);
        DB::table('finance_transactions')->insert([['type' => 'income', 'description' => 'Laundry order #014', 'amount' => 150, 'staff_id' => $staffId, 'laundry_transaction_id' => $transactionId, 'transaction_date' => today(), 'created_at' => $now, 'updated_at' => $now], ['type' => 'expense', 'description' => 'Laundry supplies', 'amount' => 650, 'staff_id' => $staffId, 'laundry_transaction_id' => null, 'transaction_date' => today(), 'created_at' => $now, 'updated_at' => $now]]);
        DB::table('staff_attendance')->insert(['staff_id' => $staffId, 'clock_in' => now()->setTime(8, 0), 'work_date' => today(), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('notifications')->insert(['type' => 'low_stock', 'message' => 'Downy Sachet is low on stock.', 'notifiable_type' => 'App\\Models\\InventoryItem', 'notifiable_id' => DB::table('inventory_items')->where('name', 'Downy Sachet')->value('id'), 'is_read' => false, 'created_at' => $now, 'updated_at' => $now]);
    }
}
