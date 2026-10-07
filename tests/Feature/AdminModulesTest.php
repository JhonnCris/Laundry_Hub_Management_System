<?php

use App\Models\Customer;
use App\Models\FinanceTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockItem(int $qty = 10): InventoryItem
{
    $category = InventoryCategory::create(['name' => 'Snack/Drink']);

    return InventoryItem::create([
        'inventory_category_id' => $category->id, 'name' => 'Water', 'unit' => 'bottle',
        'unit_price' => 20, 'quantity_on_hand' => $qty, 'low_stock_threshold' => 2,
    ]);
}

it('keeps how many were archived in the archive records', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $item = stockItem(10);

    $this->actingAs($staff)->postJson("/ajax/staff/inventory/{$item->id}/archive", ['reason' => 'expired', 'quantity' => 10])->assertOk();

    $record = $this->actingAs($staff)->getJson('/ajax/staff/bootstrap')->json('archive_records.0');
    expect($record)->toMatchArray(['item' => 'Water', 'reason' => 'expired', 'quantity' => 10])
        ->and($item->fresh()->quantity_on_hand)->toBe(0)
        ->and($item->fresh()->status)->toBe('archived_expired');
});

it('refuses to archive more than is in stock or nothing at all', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $item = stockItem(3);

    $this->actingAs($staff)->postJson("/ajax/staff/inventory/{$item->id}/archive", ['reason' => 'spoiled', 'quantity' => 4])->assertStatus(422);
    $this->actingAs($staff)->postJson("/ajax/staff/inventory/{$item->id}/archive", ['reason' => 'spoiled', 'quantity' => 0])->assertStatus(422);
    expect($item->fresh()->quantity_on_hand)->toBe(3);
});

it('only changes inventory when a recorded receipt is added to inventory, once', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = stockItem(5);

    $id = $this->actingAs($admin)->postJson('/ajax/admin/procurement', ['inventory_item_id' => $item->id, 'quantity_received' => 12])->assertCreated()->json('restock.id');
    expect($item->fresh()->quantity_on_hand)->toBe(5);

    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$id}/stock")->assertOk();
    expect($item->fresh()->quantity_on_hand)->toBe(17);

    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$id}/stock")->assertStatus(422);
    expect($item->fresh()->quantity_on_hand)->toBe(17);
});

it('rejects only pending accounts, keeps the reason and blocks their login', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pending = User::factory()->create(['role' => 'pending']);
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->postJson("/ajax/admin/users/{$staff->id}/reject", ['reason' => 'x y z'])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/users/{$pending->id}/reject", [])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/users/{$pending->id}/reject", ['reason' => 'Not a known employee'])->assertOk();
    expect($pending->fresh())->role->toBe('rejected')->rejection_reason->toBe('Not a known employee');
    $another = User::factory()->create(['role' => 'pending']);
    $this->actingAs($staff)->postJson("/ajax/admin/users/{$another->id}/reject", ['reason' => 'nope nope'])->assertForbidden();
    $this->assertModelExists($another);
});

it('lets admin add, edit and delete machines, but not delete one with order history', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $id = $this->actingAs($admin)->postJson('/ajax/admin/machines', ['name' => 'Washer 9', 'type' => 'washer', 'status' => 'available'])->assertCreated()->json('machine.id');
    $this->actingAs($admin)->postJson('/ajax/admin/machines', ['name' => 'Washer 9', 'type' => 'washer', 'status' => 'available'])->assertStatus(422);

    $this->actingAs($admin)->patchJson("/ajax/admin/machines/{$id}", ['name' => 'Washer 9', 'type' => 'dryer', 'status' => 'maintenance'])->assertOk();
    expect(Machine::find($id))->type->toBe('dryer')->status->toBe('maintenance');

    $listed = $this->actingAs($admin)->getJson('/ajax/admin/bootstrap')->json('machines');
    expect(collect($listed)->firstWhere('id', $id))->toHaveKeys(['transactions_count', 'current_order']);

    $this->actingAs($admin)->deleteJson("/ajax/admin/machines/{$id}")->assertOk();
    $this->assertModelMissing(Machine::make(['id' => $id]));
});

it('keeps machine management away from staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->postJson('/ajax/admin/machines', ['name' => 'X', 'type' => 'washer', 'status' => 'available'])->assertForbidden();
});

it('adds a receipt to inventory in parts and logs each part in the stock ledger', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = stockItem(0);
    $id = $this->actingAs($admin)->postJson('/ajax/admin/procurement', ['inventory_item_id' => $item->id, 'quantity_received' => 10, 'cost' => 100, 'invoice_number' => 'INV-1'])->assertCreated()->json('restock.id');

    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$id}/stock", ['quantity' => 11])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$id}/stock", ['quantity' => 4])->assertOk()->assertJsonPath('restock.receipt_status', 'partial');
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$id}/stock")->assertOk()->assertJsonPath('restock.receipt_status', 'stocked');
    expect($item->fresh()->quantity_on_hand)->toBe(10);

    $boot = $this->actingAs($admin)->getJson('/ajax/admin/bootstrap');
    expect(collect($boot->json('movements'))->pluck('quantity_change')->all())->toBe([6, 4])
        ->and($boot->json('movements.0.reason'))->toContain('INV-1');
});

it('voids a mistaken receipt and removes its expense, but not one already stocked', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = stockItem(0);
    $make = fn () => $this->actingAs($admin)->postJson('/ajax/admin/procurement', ['inventory_item_id' => $item->id, 'quantity_received' => 5, 'cost' => 50])->json('restock.id');

    $a = $make();
    expect(FinanceTransaction::where('inventory_restock_id', $a)->count())->toBe(1);
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$a}/void", [])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$a}/void", ['reason' => 'Typed wrong item'])->assertOk();
    expect(FinanceTransaction::where('inventory_restock_id', $a)->count())->toBe(0);
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$a}/stock")->assertStatus(422);

    $b = $make();
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$b}/stock", ['quantity' => 1])->assertOk();
    $this->actingAs($admin)->postJson("/ajax/admin/procurement/{$b}/void", ['reason' => 'Changed my mind'])->assertStatus(422);
});

it('lets admin archive stock too and shows the activity log with paging', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = stockItem(5);

    $this->actingAs($admin)->postJson("/ajax/staff/inventory/{$item->id}/archive", ['reason' => 'damaged', 'quantity' => 2])->assertOk();
    expect($this->actingAs($admin)->getJson('/ajax/admin/bootstrap')->json('archive_records.0.quantity'))->toBe(2);

    $this->actingAs($admin)->postJson('/ajax/admin/machines', ['name' => 'W1', 'type' => 'washer', 'status' => 'available']);
    $log = $this->actingAs($admin)->getJson('/ajax/admin/audit?q=machine')->assertOk();
    expect($log->json('per_page'))->toBe(8)->and($log->json('data.0.action'))->toBe('machine.created');
});

it('records sales and cancellations in the stock ledger', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $item = stockItem(10);
    $customer = Customer::create(['name' => 'Buyer', 'contact_number' => '0911']);

    $id = $this->actingAs($staff)->postJson('/ajax/staff/transactions', [
        'customer_id' => $customer->id, 'transaction_type' => 'drop_off', 'service_amount' => 0, 'total_amount' => 40,
        'cash_tendered' => 40, 'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
    ])->assertOk()->json('transaction.id');
    $this->actingAs($staff)->postJson("/ajax/staff/transactions/{$id}/cancel", ['reason' => 'Changed mind'])->assertOk();

    expect($item->adjustments()->orderBy('id')->pluck('quantity_change')->map(fn ($v) => (int) $v)->all())->toBe([-2, 2])
        ->and($item->fresh()->quantity_on_hand)->toBe(10);
});

it('lets admin change prices and bills new self-service orders at the new per-kg rate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Rate Check', 'contact_number' => '0955']);

    $id = $this->actingAs($admin)->postJson('/ajax/admin/services', ['name' => 'Wash Only', 'base_price' => 70, 'rate_per_kg' => 25, 'is_active' => true])->assertCreated()->json('service.id');
    $self = fn (float $amount) => [
        'customer_id' => $customer->id, 'transaction_type' => 'self_service', 'service_id' => $id,
        'load_weight_kg' => 4, 'service_amount' => $amount, 'total_amount' => $amount, 'cash_tendered' => 500,
    ];

    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $self(100))->assertOk();

    $this->actingAs($admin)->patchJson("/ajax/admin/services/{$id}", ['name' => 'Wash Only', 'base_price' => 80, 'rate_per_kg' => 30, 'is_active' => true])->assertOk();
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $self(100))->assertStatus(422);
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $self(120))->assertOk();

    $this->actingAs($admin)->patchJson("/ajax/admin/services/{$id}", ['name' => 'Wash Only', 'base_price' => 80, 'rate_per_kg' => 30, 'is_active' => false])->assertOk();
    expect($this->actingAs($staff)->getJson('/ajax/staff/bootstrap')->json('services'))->toBeEmpty();
    $this->actingAs($staff)->postJson('/ajax/admin/services', ['name' => 'X', 'base_price' => 1, 'is_active' => true])->assertForbidden();
});
