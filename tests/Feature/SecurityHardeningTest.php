<?php

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function snackItem(int $quantity): int
{
    $categoryId = DB::table('inventory_categories')->insertGetId(['name' => 'Snack/Drink', 'created_at' => now(), 'updated_at' => now()]);

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
        ->postJson('/api/staff/transactions', purchasePayload($itemId, 5))
        ->assertStatus(422);

    expect(DB::table('inventory_items')->where('id', $itemId)->value('quantity_on_hand'))->toBe(2);
});

it('rejects oversized quantities', function () {
    $itemId = snackItem(500);

    $this->actingAs(staffUser())
        ->postJson('/api/staff/transactions', purchasePayload($itemId, 500))
        ->assertStatus(422);
});

it('rejects the same payment token submitted twice', function () {
    $itemId = snackItem(10);
    $payload = purchasePayload($itemId, 1, ['client_token' => 'abc-123']);
    $user = staffUser();

    $this->actingAs($user)->postJson('/api/staff/transactions', $payload)->assertOk();
    $this->actingAs($user)->postJson('/api/staff/transactions', $payload)->assertStatus(409);

    expect(LaundryTransaction::count())->toBe(1);
});

it('enforces order status transitions and admin-only cancellation', function () {
    $itemId = snackItem(10);
    $staff = staffUser();
    $this->actingAs($staff)->postJson('/api/staff/transactions', purchasePayload($itemId, 1))->assertOk();
    $order = LaundryTransaction::first();

    $this->actingAs($staff)->patchJson("/api/staff/transactions/{$order->id}/status", ['status' => 'claimed'])->assertStatus(422);
    $this->actingAs($staff)->patchJson("/api/staff/transactions/{$order->id}/status", ['status' => 'cancelled'])->assertForbidden();
    $this->actingAs($staff)->patchJson("/api/staff/transactions/{$order->id}/status", ['status' => 'processing'])->assertOk();
});

it('does not allow role or verification to be mass assigned', function () {
    $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass', 'role' => 'admin', 'email_verified_at' => now()]);

    expect($user->role)->toBeNull()->and($user->email_verified_at)->toBeNull();
});

it('creates admin-made users as verified with the chosen role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/admin/users', [
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

    $this->actingAs($user)->postJson('/api/staff/transactions', $payload)->assertStatus(422);

    $payload['items'][0]['quantity'] = 1;
    $payload['total_amount'] = 10;
    $this->actingAs($user)->postJson('/api/staff/transactions', $payload)->assertOk();
});

it('shows which customer holds a basket and refuses to hand it out twice', function () {
    $itemId = snackItem(10);
    $basketId = DB::table('basket_tags')->insertGetId(['code' => '#050', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
    $staff = staffUser();
    $laundry = fn () => purchasePayload($itemId, 1, ['basket_code' => '#050']);

    $this->actingAs($staff)->postJson('/api/staff/transactions', $laundry())->assertOk();

    $bootstrap = $this->actingAs($staff)->getJson('/api/staff/bootstrap')->json();
    $basket = collect($bootstrap['baskets'])->firstWhere('id', $basketId);
    expect($basket['assigned']['customer'])->toBe('Buyer')
        ->and(collect($bootstrap['available_baskets'])->pluck('id'))->not->toContain($basketId);

    $this->actingAs($staff)->postJson('/api/staff/transactions', $laundry())->assertStatus(422);
});
