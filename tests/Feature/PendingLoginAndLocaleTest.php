<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('does not let a pending account log in, even with the right password', function () {
    User::factory()->create(['email' => 'new@example.com', 'role' => 'pending']);

    Volt::test('auth.login')
        ->set('email', 'new@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertNoRedirect();

    $this->assertGuest();
});

it('lets an approved staff account log in', function () {
    User::factory()->create(['email' => 'ok@example.com', 'role' => 'staff']);

    Volt::test('auth.login')
        ->set('email', 'ok@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertAuthenticated();
});

it('saves a preferred language and rejects unknown ones', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)->postJson('/ajax/locale', ['locale' => 'de'])->assertStatus(422);
    $this->actingAs($user)->postJson('/ajax/locale', ['locale' => 'fil'])->assertOk();

    expect($user->fresh()->locale)->toBe('fil');
    $this->postJson('/ajax/locale', ['locale' => 'en'])->assertOk();
    expect($user->fresh()->locale)->toBe('en');
});

it('shows the help guide in the user language, with a guide that matches the role', function () {
    $staff = User::factory()->create(['role' => 'staff', 'locale' => 'fil']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($staff)->get('/dashboard')
        ->assertOk()->assertSee('data-help-modal', false)->assertSee('Gabay at Tulong')->assertSee('Mag-clock in')->assertDontSee('Stock Receiving');
    $this->actingAs($admin)->get('/admin')
        ->assertOk()->assertSee('Help &amp; guides', false)->assertSee('Stock Receiving');
});
