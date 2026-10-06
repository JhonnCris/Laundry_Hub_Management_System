<?php

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function offDutyStaff(): User
{
    test()->offDuty = true;

    return User::factory()->create(['role' => 'staff']);
}

it('blocks staff from adding customers or transactions until they clock in, and again after clock out', function () {
    $user = offDutyStaff();
    Staff::create(['name' => $user->name, 'email' => $user->email, 'password' => 'x', 'role' => 'staff']);
    $customer = ['first_name' => 'Ana', 'last_name' => 'Reyes', 'contact_number' => '0917'];
    $serviceId = Service::create(['name' => 'Wash', 'base_price' => 100, 'is_active' => true])->id;
    $sale = fn () => [
        'customer_id' => Customer::create(['name' => 'Walk In', 'contact_number' => '0900'])->id,
        'transaction_type' => 'drop_off',
        'service_id' => $serviceId,
        'service_amount' => 100, 'total_amount' => 100, 'cash_tendered' => 100,
    ];

    $this->actingAs($user)->postJson('/ajax/staff/customers', $customer)->assertForbidden();
    $this->actingAs($user)->postJson('/ajax/staff/transactions', $sale())->assertForbidden();

    $this->actingAs($user)->postJson('/ajax/staff/attendance/clock-in')->assertOk();
    $this->actingAs($user)->postJson('/ajax/staff/customers', $customer)->assertCreated();
    $this->actingAs($user)->postJson('/ajax/staff/transactions', $sale())->assertOk();

    $this->actingAs($user)->postJson('/ajax/staff/attendance/clock-out')->assertOk();
    $this->actingAs($user)->postJson('/ajax/staff/customers', $customer)->assertForbidden();
    expect(StaffAttendance::count())->toBe(1);
});

it('lets an admin edit an inventory item and logs a changed quantity', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $categoryId = DB::table('inventory_categories')->insertGetId(['name' => 'Snack/Drink', 'created_at' => now(), 'updated_at' => now()]);
    $item = InventoryItem::create([
        'inventory_category_id' => $categoryId, 'name' => 'Soda', 'unit' => 'can', 'unit_price' => 20,
        'quantity_on_hand' => 10, 'low_stock_threshold' => 3,
    ]);
    $payload = [
        'name' => 'Cola', 'inventory_category_id' => $categoryId, 'unit' => 'bottle', 'unit_price' => 25.5,
        'quantity_on_hand' => 4, 'low_stock_threshold' => 5,
    ];

    $this->actingAs(User::factory()->create(['role' => 'staff']))->patchJson("/ajax/admin/inventory/{$item->id}", $payload)->assertForbidden();
    $this->actingAs($admin)->patchJson("/ajax/admin/inventory/{$item->id}", ['name' => ''] + $payload)->assertStatus(422);
    $this->actingAs($admin)->patchJson("/ajax/admin/inventory/{$item->id}", $payload)->assertOk()->assertJsonPath('item.name', 'Cola');

    expect($item->fresh()->quantity_on_hand)->toBe(4)
        ->and((float) $item->fresh()->unit_price)->toBe(25.5)
        ->and($item->adjustments()->value('quantity_change'))->toBe(-6);
});
