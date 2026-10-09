<?php

use App\Models\Customer;
use App\Models\FinanceTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\LaundryTransaction;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeMachine(string $name, string $type, string $size): int
{
    return DB::table('machines')->insertGetId(['name' => $name, 'type' => $type, 'size' => $size, 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
}

it('bills several machines at their size rates and records the machine summary', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Big Load', 'contact_number' => '0911']);
    $w1 = makeMachine('Washer 1', 'washer', 'titan');
    $w2 = makeMachine('Washer 2', 'washer', 'giant');
    $d1 = makeMachine('Dryer 1', 'dryer', 'titan');
    Service::create(['name' => 'Wash with Dry', 'base_price' => 120, 'is_active' => true]);

    // titan wash 110 + giant wash 65 + titan dry 30 min 90 = 265
    $payload = [
        'customer_id' => $customer->id, 'transaction_type' => 'self_service',
        'machines' => [['machine_id' => $w1], ['machine_id' => $w2], ['machine_id' => $d1, 'minutes' => 30]],
        'service_amount' => 265, 'total_amount' => 265, 'cash_tendered' => 300,
    ];
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $payload)->assertOk();

    $order = LaundryTransaction::first();
    expect($order->machine_summary)->toBe('2 washers · 1 dryer')
        ->and($order->machineUsages->pluck('minutes')->sort()->values()->all())->toBe([30, 38, 38])
        ->and($order->service->name)->toBe('Wash with Dry')
        ->and(DB::table('machines')->whereIn('id', [$w1, $w2, $d1])->pluck('status')->unique()->all())->toBe(['in_use']);

    $order->releaseMachines();
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'ready_for_pickup'])->assertOk();
    expect(DB::table('machines')->whereIn('id', [$w1, $w2, $d1])->pluck('status')->unique()->all())->toBe(['available']);
});

it('rejects a wrong machine charge, an invalid dryer time and a busy machine', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Checker', 'contact_number' => '0912']);
    $dryer = makeMachine('Dryer 1', 'dryer', 'giant');

    $base = ['customer_id' => $customer->id, 'transaction_type' => 'self_service', 'cash_tendered' => 500];
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $base + ['machines' => [['machine_id' => $dryer, 'minutes' => 25]], 'service_amount' => 50, 'total_amount' => 50])->assertStatus(422);
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $base + ['machines' => [['machine_id' => $dryer, 'minutes' => 20]], 'service_amount' => 1, 'total_amount' => 1])->assertStatus(422);
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $base + ['machines' => [], 'service_amount' => 0, 'total_amount' => 0])->assertStatus(422);

    $ok = $base + ['machines' => [['machine_id' => $dryer, 'minutes' => 20]], 'service_amount' => 50, 'total_amount' => 50];
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $ok)->assertOk();
    $this->actingAs($staff)->postJson('/ajax/staff/transactions', $ok)->assertStatus(422);
});

it('accepts drop-off laundry unpaid and takes the payment on release', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Pay Later', 'contact_number' => '0913']);
    $service = Service::create(['name' => 'Wash', 'base_price' => 100, 'is_active' => true]);

    $this->actingAs($staff)->postJson('/ajax/staff/transactions', [
        'customer_id' => $customer->id, 'transaction_type' => 'drop_off', 'service_id' => $service->id,
        'service_amount' => 100, 'total_amount' => 100, 'pay_later' => true,
    ])->assertOk();

    $order = LaundryTransaction::first();
    expect($order->payment_status)->toBe('unpaid')->and(FinanceTransaction::count())->toBe(0);

    foreach (['processing', 'ready_for_pickup'] as $status) {
        $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => $status])->assertOk();
    }
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'claimed'])->assertStatus(422);
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'claimed', 'cash_tendered' => 50])->assertStatus(422);
    $this->actingAs($staff)->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'claimed', 'cash_tendered' => 150])->assertOk()->assertJsonPath('change_given', 50);

    expect($order->fresh()->payment_status)->toBe('paid')->and((float) FinanceTransaction::sum('amount'))->toBe(100.0);
});

it('only lets drop-off laundry be paid later', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'No Credit', 'contact_number' => '0914']);
    $washer = makeMachine('Washer 1', 'washer', 'giant');

    $this->actingAs($staff)->postJson('/ajax/staff/transactions', [
        'customer_id' => $customer->id, 'transaction_type' => 'self_service', 'machines' => [['machine_id' => $washer]],
        'service_amount' => 65, 'total_amount' => 65, 'pay_later' => true,
    ])->assertStatus(422);
});

it('lets admin manage categories and machine rates', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $id = $this->actingAs($admin)->postJson('/ajax/admin/categories', ['name' => 'Fabric conditioner'])->assertCreated()->json('category.id');
    $this->actingAs($admin)->postJson('/ajax/admin/categories', ['name' => 'Fabric conditioner'])->assertStatus(422);
    $this->actingAs($admin)->patchJson("/ajax/admin/categories/{$id}", ['name' => 'Softener'])->assertOk();

    InventoryItem::create(['inventory_category_id' => $id, 'name' => 'Downy', 'unit' => 'sachet', 'unit_price' => 10, 'quantity_on_hand' => 1, 'low_stock_threshold' => 1]);
    $this->actingAs($admin)->deleteJson("/ajax/admin/categories/{$id}")->assertStatus(422);
    $empty = InventoryCategory::create(['name' => 'Empty'])->id;
    $this->actingAs($admin)->deleteJson("/ajax/admin/categories/{$empty}")->assertOk();

    $this->actingAs($admin)->postJson('/ajax/admin/machine-rates', ['size' => 'titan', 'kind' => 'wash', 'price' => 120])->assertOk();
    $this->actingAs($admin)->postJson('/ajax/admin/machine-rates', ['size' => 'titan', 'kind' => 'dry', 'minutes' => 15, 'price' => 70])->assertStatus(422);
    $this->actingAs($admin)->postJson('/ajax/admin/machine-rates', ['size' => 'titan', 'kind' => 'dry', 'minutes' => 10, 'price' => 30])->assertOk();

    $rates = collect($this->actingAs($admin)->getJson('/ajax/admin/bootstrap')->json('machine_rates'));
    expect((float) $rates->where('size', 'titan')->where('kind', 'wash')->first()['price'])->toBe(120.0)
        ->and($rates->where('size', 'titan')->where('kind', 'dry')->pluck('minutes')->all())->toBe([10, 20, 30, 40]);
});
