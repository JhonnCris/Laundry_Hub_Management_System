<?php

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function paidOrder(User $staff, int $quantity = 2): array
{
    $categoryId = DB::table('inventory_categories')->insertGetId(['name' => 'Snack/Drink', 'created_at' => now(), 'updated_at' => now()]);
    $itemId = DB::table('inventory_items')->insertGetId([
        'inventory_category_id' => $categoryId, 'name' => 'Soda', 'unit' => 'can', 'unit_price' => 20,
        'quantity_on_hand' => 10, 'low_stock_threshold' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customer = Customer::create(['name' => 'Cancel Me', 'contact_number' => '0911']);

    test()->actingAs($staff)->postJson('/ajax/staff/transactions', [
        'customer_id' => $customer->id,
        'transaction_type' => 'drop_off',
        'service_amount' => 0,
        'items' => [['inventory_item_id' => $itemId, 'quantity' => $quantity]],
        'total_amount' => 20 * $quantity,
        'cash_tendered' => 100,
    ])->assertOk();

    return [LaundryTransaction::first(), $itemId];
}

it('cancels an order, returns stock and moves it to the archive records', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    [$order, $itemId] = paidOrder($staff);

    expect(DB::table('inventory_items')->where('id', $itemId)->value('quantity_on_hand'))->toBe(8);

    $before = $this->actingAs($admin)->getJson('/ajax/admin/bootstrap')->json();
    expect($before['metrics']['period_sales'])->toEqual(40);

    $this->actingAs($admin)
        ->postJson("/ajax/admin/transactions/{$order->id}/cancel", ['reason' => 'Customer changed mind'])
        ->assertOk();

    expect(DB::table('inventory_items')->where('id', $itemId)->value('quantity_on_hand'))->toBe(10);

    $after = $this->actingAs($admin)->getJson('/ajax/admin/bootstrap')->json();
    expect($after['metrics']['period_sales'])->toEqual(0)
        ->and($after['metrics']['paid_orders'])->toBe(0)
        ->and($after['metrics']['cancelled_orders'])->toBe(1)
        ->and($after['cancelled_orders'][0]['notes'])->toContain('Customer changed mind')
        ->and(collect($after['recent_orders'])->pluck('id'))->not->toContain($order->id)
        ->and(collect($after['finance'])->where('type', 'income'))->toBeEmpty();
});

it('only lets admins cancel and requires a reason', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    [$order] = paidOrder($staff);

    $this->actingAs($staff)->postJson("/ajax/admin/transactions/{$order->id}/cancel", ['reason' => 'nope'])->assertForbidden();
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'cancelled'])->assertForbidden();
    $this->actingAs($admin)->postJson("/ajax/admin/transactions/{$order->id}/cancel", [])->assertStatus(422);
});

it('cannot cancel a claimed order', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    [$order] = paidOrder($staff);
    $order->update(['status' => 'claimed']);

    $this->actingAs($admin)
        ->postJson("/ajax/admin/transactions/{$order->id}/cancel", ['reason' => 'too late'])
        ->assertStatus(422);
});

it('keeps the staff record linked when a user changes their email', function () {
    $user = User::factory()->create(['role' => 'staff', 'email' => 'old@example.com']);
    Staff::create(['name' => $user->name, 'email' => 'old@example.com', 'password' => 'x', 'role' => 'staff']);

    $user->update(['email' => 'new@example.com']);

    expect(Staff::where('email', 'new@example.com')->exists())->toBeTrue()
        ->and(Staff::where('email', 'old@example.com')->exists())->toBeFalse();
});

it('blocks an email that already belongs to a staff record', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Staff::create(['name' => 'Ghost', 'email' => 'ghost@example.com', 'password' => 'x', 'role' => 'staff']);

    $this->actingAs($admin)->postJson('/ajax/admin/users', [
        'name' => 'Imposter', 'email' => 'ghost@example.com', 'password' => 'password123', 'role' => 'staff',
    ])->assertStatus(422);
});

it('answers registration the same way whether or not the email exists', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $existing = Volt::test('auth.register')
        ->set('name', 'Someone')->set('email', 'taken@example.com')
        ->set('password', 'password')->set('password_confirmation', 'password')
        ->call('register');
    $fresh = Volt::test('auth.register')
        ->set('name', 'Someone New')->set('email', 'fresh@example.com')
        ->set('password', 'password')->set('password_confirmation', 'password')
        ->call('register');

    $existing->assertHasNoErrors()->assertRedirect(route('login', absolute: false));
    $fresh->assertHasNoErrors()->assertRedirect(route('login', absolute: false));
    expect(User::where('email', 'taken@example.com')->count())->toBe(1)
        ->and(User::where('email', 'fresh@example.com')->exists())->toBeTrue();
});
