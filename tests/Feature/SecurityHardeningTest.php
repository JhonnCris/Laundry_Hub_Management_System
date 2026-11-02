<?php

use App\Models\Customer;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\LaundryTransaction;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function snackItem(int $quantity): int
{
    $categoryId = InventoryCategory::query()->firstOrCreate(['name' => 'Snack/Drink'])->id;

    return DB::table('inventory_items')->insertGetId([
        'inventory_category_id' => $categoryId, 'name' => 'Chips', 'unit' => 'pack',
        'unit_price' => 10, 'quantity_on_hand' => $quantity, 'low_stock_threshold' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function purchasePayload(int $itemId, int $qty, array $extra = []): array
{
    $customer = Customer::create(['name' => 'Buyer', 'contact_number' => '0900']);

    return array_merge([
        'customer_id' => $customer->id,
        'transaction_type' => 'drop_off',
        'service_amount' => 0,
        'items' => [['inventory_item_id' => $itemId, 'quantity' => $qty]],
        'total_amount' => 10 * $qty,
        'cash_tendered' => 1000,
    ], $extra);
}

function staffUser(): User
{
    return User::factory()->create(['role' => 'staff']);
}

it('sends security headers', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('rejects an order for more stock than exists', function () {
    $itemId = snackItem(2);

    $this->actingAs(staffUser())
        ->postJson('/ajax/staff/transactions', purchasePayload($itemId, 5))
        ->assertStatus(422);

    expect(DB::table('inventory_items')->where('id', $itemId)->value('quantity_on_hand'))->toBe(2);
});

it('rejects archived inventory items in a sale', function () {
    $itemId = snackItem(10);
    DB::table('inventory_items')->where('id', $itemId)->update(['status' => 'archived']);

    $this->actingAs(staffUser())
        ->postJson('/ajax/staff/transactions', purchasePayload($itemId, 1))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.inventory_item_id');

    expect(LaundryTransaction::count())->toBe(0)
        ->and(DB::table('inventory_items')->where('id', $itemId)->value('quantity_on_hand'))->toBe(10);
});

it('lists priced supot as a counter item and excludes products without a sale price', function () {
    $supply = InventoryCategory::query()->firstOrCreate(['name' => 'Supply']);
    $pricedItem = InventoryItem::query()->create([
        'inventory_category_id' => $supply->id,
        'name' => 'Supot - transparent',
        'unit' => 'piece',
        'unit_price' => 4,
        'quantity_on_hand' => 8,
        'low_stock_threshold' => 1,
    ]);
    InventoryItem::query()->create([
        'inventory_category_id' => $supply->id,
        'name' => 'Unpriced supply',
        'unit' => 'piece',
        'unit_price' => 0,
        'quantity_on_hand' => 8,
        'low_stock_threshold' => 1,
    ]);

    $items = $this->actingAs(staffUser())
        ->getJson('/ajax/staff/bootstrap')
        ->assertOk()
        ->json('snacks');

    expect(collect($items)->pluck('id')->all())->toContain($pricedItem->id)
        ->and(collect($items)->pluck('name')->all())->not->toContain('Unpriced supply');
});

it('syncs only the September workbook products with listed prices', function () {
    $prices = InventoryItem::query()
        ->whereIn('name', ['Supot - transparent', 'Supot - blue', 'Zonrox', 'Zonrox ColorSafe', 'Water', 'Water Big'])
        ->pluck('unit_price', 'name')
        ->map(fn ($price) => (float) $price)
        ->all();

    expect($prices)->toMatchArray([
        'Supot - transparent' => 4.0,
        'Supot - blue' => 14.0,
        'Zonrox' => 8.0,
        'Zonrox ColorSafe' => 10.0,
        'Water' => 20.0,
        'Water Big' => 30.0,
    ]);
});

it('records supot with a laundry service in one paid transaction', function () {
    $supply = InventoryCategory::query()->firstOrCreate(['name' => 'Supply']);
    $item = InventoryItem::query()->create([
        'inventory_category_id' => $supply->id,
        'name' => 'Supot - transparent',
        'unit' => 'piece',
        'unit_price' => 4,
        'quantity_on_hand' => 5,
        'low_stock_threshold' => 1,
    ]);
    $service = Service::query()->create(['name' => 'Drop-off test', 'base_price' => 30, 'is_active' => true]);
    $payload = purchasePayload($item->id, 2, [
        'service_id' => $service->id,
        'service_amount' => 30,
        'total_amount' => 38,
        'cash_tendered' => 50,
    ]);

    $transactionId = $this->actingAs(staffUser())
        ->postJson('/ajax/staff/transactions', $payload)
        ->assertOk()
        ->json('transaction.id');

    $transaction = LaundryTransaction::query()->with('inventoryItems')->findOrFail($transactionId);
    $line = $transaction->inventoryItems->first();

    expect((float) $transaction->total_amount)->toBe(38.0)
        ->and($transaction->payment_status)->toBe('paid')
        ->and($line->pivot->quantity)->toBe(2)
        ->and((float) $line->pivot->line_total)->toBe(8.0)
        ->and($item->fresh()->quantity_on_hand)->toBe(3);
});

it('rejects sales of inventory without a configured price', function () {
    $itemId = snackItem(5);
    DB::table('inventory_items')->where('id', $itemId)->update(['unit_price' => 0]);

    $this->actingAs(staffUser())
        ->postJson('/ajax/staff/transactions', purchasePayload($itemId, 1, [
            'total_amount' => 0,
            'cash_tendered' => 0,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');

    expect(LaundryTransaction::count())->toBe(0);
});

it('rejects archived detergent items when charging them to a laundry order', function () {
    $categoryId = DB::table('inventory_categories')->where('name', 'Detergent')->value('id');
    $itemId = DB::table('inventory_items')->insertGetId([
        'inventory_category_id' => $categoryId,
        'name' => 'Archived detergent',
        'unit' => 'sachet',
        'unit_price' => 10,
        'quantity_on_hand' => 10,
        'low_stock_threshold' => 1,
        'status' => 'archived',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(staffUser())
        ->postJson('/ajax/staff/transactions', purchasePayload($itemId, 1, [
            'items' => [],
            'total_amount' => 10,
            'detergent_item_id' => $itemId,
            'detergent_quantity' => 1,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('detergent_item_id');

    expect(LaundryTransaction::count())->toBe(0)
        ->and(DB::table('inventory_items')->where('id', $itemId)->value('quantity_on_hand'))->toBe(10);
});

it('rejects oversized quantities', function () {
    $itemId = snackItem(500);

    $this->actingAs(staffUser())
        ->postJson('/ajax/staff/transactions', purchasePayload($itemId, 500))
        ->assertStatus(422);
});

it('rejects the same payment token submitted twice', function () {
    $itemId = snackItem(10);
    $payload = purchasePayload($itemId, 1, ['client_token' => 'abc-123']);
    $user = staffUser();

    $this->actingAs($user)->postJson('/ajax/staff/transactions', $payload)->assertOk();
    $this->actingAs($user)->postJson('/ajax/staff/transactions', $payload)->assertStatus(409);

    expect(LaundryTransaction::count())->toBe(1);
});

it('enforces order status transitions and keeps cancellation off the status endpoint', function () {
    $itemId = snackItem(10);
    $staff = staffUser();
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', purchasePayload($itemId, 1, ['garments' => [['name' => 'Shirt', 'quantity' => 1]]]))->assertOk();
    $order = LaundryTransaction::first();

    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'claimed'])->assertStatus(422);
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(422);
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'processing'])->assertOk();
});

it('does not allow role or verification to be mass assigned', function () {
    $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass', 'role' => 'admin', 'email_verified_at' => now()]);

    expect($user->role)->toBeNull()->and($user->email_verified_at)->toBeNull();
});

it('creates admin-made users as verified with the chosen role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/ajax/admin/users', [
        'name' => 'New Staff', 'email' => 'new@example.com', 'password' => 'password123', 'role' => 'staff',
    ])->assertCreated();

    $created = User::where('email', 'new@example.com')->first();
    expect($created->role)->toBe('staff')->and($created->email_verified_at)->not->toBeNull();
});

it('seeds accounts without the well-known password', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@ssklabadami.test')->first();
    expect(Hash::check('password', $admin->password))->toBeFalse();
});

it('lets the cashier retry with the same token after a failed (unsaved) payment', function () {
    $itemId = snackItem(2);
    $payload = purchasePayload($itemId, 5, ['client_token' => 'retry-1']);
    $user = staffUser();

    $this->actingAs($user)->postJson('/ajax/staff/transactions', $payload)->assertStatus(422);

    $payload['items'][0]['quantity'] = 1;
    $payload['total_amount'] = 10;
    $this->actingAs($user)->postJson('/ajax/staff/transactions', $payload)->assertOk();
});

it('shows which customer holds a basket and refuses to hand it out twice', function () {
    $itemId = snackItem(10);
    $basketId = DB::table('basket_tags')->insertGetId(['code' => '#050', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
    $staff = staffUser();
    $laundry = fn () => purchasePayload($itemId, 1, ['basket_code' => '#050', 'garments' => [['name' => 'Shirt', 'quantity' => 1]]]);

    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $laundry())->assertOk();

    $bootstrap = $this->actingAs($staff)->getJson('/ajax/staff/bootstrap')->json();
    $basket = collect($bootstrap['baskets'])->firstWhere('id', $basketId);
    expect($basket['assigned']['customer'])->toBe('Buyer')
        ->and(collect($bootstrap['available_baskets'])->pluck('id'))->not->toContain($basketId);

    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $laundry())->assertStatus(422);
});
