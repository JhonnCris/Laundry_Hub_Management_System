<?php

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function lowItem(int $qty = 1, int $threshold = 5): int
{
    $categoryId = DB::table('inventory_categories')->insertGetId(['name' => 'Detergent', 'created_at' => now(), 'updated_at' => now()]);

    return DB::table('inventory_items')->insertGetId([
        'inventory_category_id' => $categoryId, 'name' => 'Ariel', 'unit' => 'sachet', 'unit_price' => 10,
        'quantity_on_hand' => $qty, 'low_stock_threshold' => $threshold, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('lets staff flag a low-stock item once and shows it in the admin alerts', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $itemId = lowItem();

    $this->actingAs($staff)->postJson("/api/staff/inventory/{$itemId}/notify-low")->assertOk();
    $this->actingAs($staff)->postJson("/api/staff/inventory/{$itemId}/notify-low")->assertOk();

    expect(Notification::where('type', 'low_stock')->count())->toBe(1);

    $this->actingAs($staff)->getJson('/api/staff/bootstrap')->assertJsonPath('reported_low_stock_ids.0', $itemId);
    $reports = $this->actingAs($admin)->getJson('/api/admin/bootstrap')->json('staff_reports');
    expect($reports)->toHaveCount(1)->and($reports[0]['message'])->toContain('Ariel');
});

it('refuses to flag an item that is not low', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $itemId = lowItem(qty: 50);

    $this->actingAs($staff)->postJson("/api/staff/inventory/{$itemId}/notify-low")->assertStatus(422);
});

it('resolves the staff report when the admin records a receipt, and lets admin dismiss alerts', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $itemId = lowItem();
    $this->actingAs($staff)->postJson("/api/staff/inventory/{$itemId}/notify-low")->assertOk();

    $this->actingAs($admin)->postJson('/api/admin/procurement', ['inventory_item_id' => $itemId, 'quantity_received' => 20])->assertCreated();
    expect(Notification::where('is_read', false)->count())->toBe(0);

    $note = Notification::create(['type' => 'low_stock', 'message' => 'x', 'is_read' => false]);
    $this->actingAs($admin)->postJson("/api/admin/notifications/{$note->id}/read")->assertOk();
    expect($note->fresh()->is_read)->toBeTrue();
    $this->actingAs($staff)->postJson("/api/admin/notifications/{$note->id}/read")->assertForbidden();
});

it('returns only claimed orders in the laundry history', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Done Customer', 'contact_number' => '0922']);
    $make = fn (string $status) => LaundryTransaction::create([
        'customer_id' => $customer->id, 'transaction_type' => 'drop_off', 'status' => $status,
        'payment_status' => 'paid', 'subtotal' => 50, 'total_amount' => 50,
    ]);
    $make('claimed');
    $make('pending');

    $orders = $this->actingAs($staff)->getJson('/api/staff/history')->assertOk()->json('orders');
    expect($orders)->toHaveCount(1)->and($orders[0]['status'])->toBe('claimed');
});

it('records who exported which report and blocks staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->postJson('/api/admin/exports', ['report' => 'sales', 'from' => '2026-10-01', 'to' => '2026-10-03'])->assertOk();
    $this->actingAs($admin)->postJson('/api/admin/exports', ['report' => 'bogus'])->assertStatus(422);
    $this->actingAs($staff)->postJson('/api/admin/exports', ['report' => 'sales'])->assertForbidden();
});

it('accepts only pdf or csv as the export file type', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/admin/exports', ['report' => 'sales', 'format' => 'pdf'])->assertOk();
    $this->actingAs($admin)->postJson('/api/admin/exports', ['report' => 'sales', 'format' => 'csv'])->assertOk();
    $this->actingAs($admin)->postJson('/api/admin/exports', ['report' => 'sales', 'format' => 'exe'])->assertStatus(422);
});
