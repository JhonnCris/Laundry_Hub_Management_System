<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('inventory_categories')->insertOrIgnore([
            ['name' => 'Detergent', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Supply', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Snack/Drink', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $categoryIds = DB::table('inventory_categories')->pluck('id', 'name');
        $products = [
            ['name' => 'Ariel', 'aliases' => ['Ariel Sachet'], 'category' => 'Detergent', 'unit' => 'sachet', 'price' => 10],
            ['name' => 'Downy', 'aliases' => ['Downy Sachet'], 'category' => 'Detergent', 'unit' => 'sachet', 'price' => 14],
            ['name' => 'Zonrox', 'aliases' => [], 'category' => 'Detergent', 'unit' => 'piece', 'price' => 8],
            ['name' => 'Zonrox ColorSafe', 'aliases' => [], 'category' => 'Detergent', 'unit' => 'bottle', 'price' => 10],
            ['name' => 'Zonrox big bottle', 'aliases' => [], 'category' => 'Detergent', 'unit' => 'bottle', 'price' => 38],
            ['name' => 'Supot - transparent', 'aliases' => [], 'category' => 'Supply', 'unit' => 'piece', 'price' => 4],
            ['name' => 'Supot - blue', 'aliases' => [], 'category' => 'Supply', 'unit' => 'piece', 'price' => 14],
            ['name' => 'Water Big', 'aliases' => ['Water big'], 'category' => 'Snack/Drink', 'unit' => 'bottle', 'price' => 30],
            ['name' => 'Water', 'aliases' => [], 'category' => 'Snack/Drink', 'unit' => 'bottle', 'price' => 20],
        ];

        foreach ($products as $product) {
            $names = array_merge([$product['name']], $product['aliases']);
            $matches = DB::table('inventory_items')
                ->whereIn('name', $names)
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$product['name']])
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                DB::table('inventory_items')->insert([
                    'inventory_category_id' => $categoryIds[$product['category']],
                    'name' => $product['name'],
                    'unit' => $product['unit'],
                    'unit_price' => $product['price'],
                    'quantity_on_hand' => 0,
                    'low_stock_threshold' => 5,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $primary = $matches->first();
            DB::table('inventory_items')->where('id', $primary->id)->update([
                'inventory_category_id' => $categoryIds[$product['category']],
                'name' => $product['name'],
                'unit' => $product['unit'],
                'unit_price' => $product['price'],
                'status' => 'active',
                'updated_at' => $now,
            ]);

            $duplicateIds = $matches->skip(1)->pluck('id');
            if ($duplicateIds->isNotEmpty()) {
                DB::table('inventory_items')->whereIn('id', $duplicateIds)->update([
                    'status' => 'archived',
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep product and stock history intact on rollback.
    }
};
