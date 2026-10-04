<?php

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pricingPayload(array $overrides = []): array
{
    $customer = Customer::create(['name' => 'Test Customer', 'contact_number' => '0900']);
    $service = Service::create(['name' => 'Wash', 'base_price' => 100, 'is_active' => true]);

    return array_merge([
        'customer_id' => $customer->id,
        'transaction_type' => 'drop_off',
        'service_id' => $service->id,
        'service_amount' => 100,
        'total_amount' => 100,
        'cash_tendered' => 200,
    ], $overrides);
}

it('saves a transaction whose total matches stored prices', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->postJson('/ajax/staff/transactions', pricingPayload())
        ->assertOk();
});

it('rejects a tampered total', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->postJson('/ajax/staff/transactions', pricingPayload(['total_amount' => 1, 'cash_tendered' => 1]))
        ->assertStatus(422);
});

it('rejects a self-service charge that does not match a known rate', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->postJson('/ajax/staff/transactions', pricingPayload([
            'transaction_type' => 'self_service',
            'service_id' => null,
            'load_weight_kg' => 5,
            'service_amount' => 1,
            'total_amount' => 1,
            'cash_tendered' => 1,
        ]))
        ->assertStatus(422);
});
