<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('applies the strict content security policy in production, but not to the Firebase sign-up page', function () {
    app()['env'] = 'production';

    $csp = $this->get('/login')->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("connect-src 'self'")
        ->and($csp)->toContain("frame-src 'none'");

    $this->get('/firebase-messaging-sw.js')->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
});

it('keeps the strict policy out of local development so the Vite dev server still works', function () {
    expect($this->get('/login')->headers->get('Content-Security-Policy'))->not->toContain("default-src 'self'");
});

it('marks private pages and API responses as not cacheable', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    expect($this->actingAs($staff)->getJson('/ajax/staff/bootstrap')->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    expect($this->actingAs($staff)->get('/dashboard')->headers->get('Cache-Control'))->toContain('no-store');
});

it('forces debug off in production even if it was switched on by mistake', function () {
    config(['app.debug' => true]);
    app()['env'] = 'production';

    (new AppServiceProvider(app()))->boot();

    expect(config('app.debug'))->toBeFalse();
});

it('blocks one address after 20 failed sign-ins, even with different emails', function () {
    $last = null;
    foreach (range(1, 21) as $i) {
        $last = Volt::test('auth.login')
            ->set('email', "nobody{$i}@example.com")
            ->set('password', 'wrong-password')
            ->call('login');
    }

    $last->assertHasErrors(['email']);
    expect(collect($last->errors()->get('email'))->first())->toContain('Too many login attempts');
});
