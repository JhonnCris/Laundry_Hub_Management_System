<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staffForNames(): User
{
    return User::factory()->create(['role' => 'staff']);
}

it('stores the name as first, middle and last parts and builds the display name', function () {
    $response = $this->actingAs(staffForNames())->postJson('/ajax/staff/customers', [
        'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz', 'contact_number' => '09171234567',
    ])->assertCreated();

    $customer = Customer::find($response->json('customer.id'));
    expect($customer->first_name)->toBe('Juan')
        ->and($customer->middle_name)->toBe('Dela')
        ->and($customer->last_name)->toBe('Cruz')
        ->and($customer->name)->toBe('Juan Dela Cruz');
});

it('works without a middle name and trims stray spaces', function () {
    $response = $this->actingAs(staffForNames())->postJson('/ajax/staff/customers', [
        'first_name' => '  Ana ', 'last_name' => ' Reyes ', 'contact_number' => '0917',
    ])->assertCreated();

    expect($response->json('customer.name'))->toBe('Ana Reyes')
        ->and($response->json('customer.middle_name'))->toBeNull();
});

it('requires a first and last name', function () {
    $staff = staffForNames();

    $this->actingAs($staff)->postJson('/ajax/staff/customers', ['first_name' => 'Juan', 'contact_number' => '0917'])
        ->assertStatus(422)->assertJsonValidationErrors('last_name');
    $this->actingAs($staff)->postJson('/ajax/staff/customers', ['last_name' => 'Cruz', 'contact_number' => '0917'])
        ->assertStatus(422)->assertJsonValidationErrors('first_name');
});

it('still accepts a customer created with only a display name (older records)', function () {
    $customer = Customer::create(['name' => 'Legacy Customer', 'contact_number' => '0900']);

    expect($customer->fresh()->name)->toBe('Legacy Customer')->and($customer->first_name)->toBeNull();
});
