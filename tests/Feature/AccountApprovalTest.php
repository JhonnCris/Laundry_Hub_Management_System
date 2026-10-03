<?php

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('registers new accounts as pending', function () {
    Volt::test('auth.register')
        ->set('name', 'New Hire')
        ->set('email', 'hire@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    expect(User::where('email', 'hire@example.com')->value('role'))->toBe('pending');
});

it('blocks pending accounts from the staff api and shows the approval page', function () {
    $pending = User::factory()->create(['role' => 'pending']);

    $this->actingAs($pending)->getJson('/api/staff/bootstrap')->assertForbidden();
    $this->actingAs($pending)->get('/dashboard')->assertOk()->assertSee('Awaiting admin approval');
});

it('lets staff use the staff api', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->getJson('/api/staff/bootstrap')
        ->assertOk();
});

it('lets an admin approve a pending account and links a staff record', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pending = User::factory()->create(['role' => 'pending']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$pending->id}", ['role' => 'staff'])
        ->assertOk();

    expect($pending->fresh()->role)->toBe('staff');
    expect(Staff::where('email', $pending->email)->exists())->toBeTrue();
});

it('stops an admin from removing their own admin access', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$admin->id}", ['role' => 'staff'])
        ->assertStatus(422);
});

it('blocks staff from the admin api', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->getJson('/api/admin/bootstrap')
        ->assertForbidden();
});
