<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('shows live public supply prices and excludes archived inventory', function () {
    $categoryId = DB::table('inventory_categories')->where('name', 'Supply')->value('id');
    DB::table('inventory_items')->insert([
        [
            'inventory_category_id' => $categoryId,
            'name' => 'Public sample supply',
            'unit' => 'bottle',
            'unit_price' => 17.5,
            'quantity_on_hand' => 3,
            'low_stock_threshold' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'inventory_category_id' => $categoryId,
            'name' => 'Archived sample supply',
            'unit' => 'piece',
            'unit_price' => 99,
            'quantity_on_hand' => 10,
            'low_stock_threshold' => 1,
            'status' => 'archived',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'inventory_category_id' => $categoryId,
            'name' => 'Unpriced sample supply',
            'unit' => 'piece',
            'unit_price' => 0,
            'quantity_on_hand' => 10,
            'low_stock_threshold' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->get('/products-services')
        ->assertOk()
        ->assertSee('Public sample supply')
        ->assertSee('₱17.50 / bottle')
        ->assertSee('In stock')
        ->assertDontSee('Archived sample supply')
        ->assertDontSee('Unpriced sample supply');
});

it('shows the current hours, contact details and directions on public pages', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Monday–Saturday')
        ->assertSee('7:30 am–5:30 pm')
        ->assertSee('0943 311 1830')
        ->assertSee('tel:+63943311830')
        ->assertSee('SSK Laba Dami Laundryhub')
        ->assertSee('Get directions')
        ->assertSee('Nabua Subdivision');
});
