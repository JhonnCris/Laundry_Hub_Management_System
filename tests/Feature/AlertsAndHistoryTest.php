<?php

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

it('loads the items, garments, detergent and cashier needed to reprint a receipt', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Receipt Customer', 'contact_number' => '0933']);
    $order = LaundryTransaction::create([
        'customer_id' => $customer->id, 'transaction_type' => 'drop_off', 'status' => 'claimed',
        'payment_status' => 'paid', 'subtotal' => 50, 'total_amount' => 50,
    ]);

    $json = $this->actingAs($staff)->getJson('/api/staff/history')->assertOk()->json('orders.0');

    expect($json)->toHaveKeys(['inventory_items', 'garment_types', 'detergent', 'handled_by'])
        ->and($json['id'])->toBe($order->id);
});

it('sends the receipt SMS through the configured provider and reports the result', function () {
    config(['services.sms.driver' => 'android', 'services.sms.android_url' => 'http://phone.local:8080/message']);
    Http::fake(['phone.local:8080/*' => Http::response([], 202)]);
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'Texted', 'contact_number' => '09602457559']);
    $order = LaundryTransaction::create([
        'customer_id' => $customer->id, 'transaction_type' => 'drop_off', 'status' => 'pending',
        'payment_status' => 'paid', 'subtotal' => 50, 'total_amount' => 50,
    ]);

    $this->actingAs($staff)->postJson("/api/staff/transactions/{$order->id}/receipt-sms")
        ->assertOk()->assertJsonPath('result', 'sent');
    Http::assertSent(fn ($r) => $r['phoneNumbers'] === ['+639602457559'] && str_contains($r['message'], "#{$order->id}"));

    $noPhone = LaundryTransaction::create([
        'customer_id' => Customer::create(['name' => 'No Phone'])->id, 'transaction_type' => 'drop_off', 'status' => 'pending',
        'payment_status' => 'paid', 'subtotal' => 1, 'total_amount' => 1,
    ]);
    $this->actingAs($staff)->postJson("/api/staff/transactions/{$noPhone->id}/receipt-sms")->assertStatus(422);
});

it('gives drop-off orders waiting for pickup a signed notify link when Firebase web settings exist', function () {
    config(['services.fcm.project_id' => 'demo', 'services.fcm.web' => ['apiKey' => 'k', 'authDomain' => null, 'messagingSenderId' => '1', 'appId' => 'a', 'vapidKey' => 'v']]);
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = Customer::create(['name' => 'QR Customer', 'contact_number' => '0944']);
    $make = fn (string $status, string $type = 'drop_off') => LaundryTransaction::create([
        'customer_id' => $customer->id, 'transaction_type' => $type, 'status' => $status,
        'payment_status' => 'paid', 'subtotal' => 10, 'total_amount' => 10,
    ]);
    $make('processing');
    $make('processing', 'self_service');
    $make('claimed');

    $bootstrap = $this->actingAs($staff)->getJson('/api/staff/bootstrap')->json('active_laundry');
    $byType = collect($bootstrap)->keyBy('transaction_type');
    expect($byType['drop_off']['notify_url'])->toContain('/notify/')->toContain('signature=')
        ->and($byType['self_service'])->not->toHaveKey('notify_url');

    $history = $this->actingAs($staff)->getJson('/api/staff/history')->json('orders');
    expect($history[0])->not->toHaveKey('notify_url');
});
