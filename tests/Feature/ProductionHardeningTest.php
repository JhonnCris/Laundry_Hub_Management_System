<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('applies a nonce-based strict content security policy in production', function () {
    app()['env'] = 'production';

    $login = $this->get('/login');
    $csp = $login->headers->get('Content-Security-Policy');
    $nonce = Vite::cspNonce();
    $scriptPolicy = collect(explode('; ', $csp))
        ->first(fn (string $directive) => str_starts_with($directive, 'script-src '));
    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("connect-src 'self'")
        ->and($csp)->toContain("frame-src 'none'")
        ->and($csp)->toContain("'nonce-{$nonce}'")
        ->and($scriptPolicy)->not->toContain("'unsafe-inline'")
        ->and($scriptPolicy)->not->toContain('cdn.jsdelivr.net');
    expect($login->getContent())->toContain('nonce="'.$nonce.'"');

    config([
        'services.fcm.project_id' => 'laundry-test',
        'services.fcm.web.apiKey' => 'test-api-key',
        'services.fcm.web.appId' => 'test-app-id',
        'services.fcm.web.messagingSenderId' => 'test-sender-id',
        'services.fcm.web.vapidKey' => 'test-vapid-key',
    ]);

    $workerResponse = $this->get('/firebase-messaging-sw.js')->assertOk();
    $workerPolicy = $workerResponse->headers->get('Content-Security-Policy');
    $workerScriptPolicy = collect(explode('; ', $workerPolicy))
        ->first(fn (string $directive) => str_starts_with($directive, 'script-src '));
    expect($workerPolicy)->toContain("default-src 'self'")
        ->toContain('https://www.gstatic.com')
        ->and($workerScriptPolicy)->not->toContain("'unsafe-inline'")
        ->not->toContain('cdn.jsdelivr.net');
});

it('allows invoice OCR assets only on the admin page under the production policy', function () {
    app()['env'] = 'production';

    $admin = User::factory()->create(['role' => 'admin']);
    $adminPolicy = $this->actingAs($admin)->get('/admin')->headers->get('Content-Security-Policy');
    $scriptPolicy = collect(explode('; ', $adminPolicy))
        ->first(fn (string $directive) => str_starts_with($directive, 'script-src '));
    expect(str_contains($scriptPolicy, "script-src 'self' 'nonce-"))->toBeTrue()
        ->and($scriptPolicy)->not->toContain("'unsafe-inline'")
        ->and(str_contains($adminPolicy, 'https://cdn.jsdelivr.net/npm/tesseract.js@v7.0.0/dist/worker.min.js https://cdn.jsdelivr.net/npm/tesseract.js-core@v7.0.0/'))->toBeTrue()
        ->and(str_contains($adminPolicy, "connect-src 'self' https://cdn.jsdelivr.net/npm/tesseract.js@v7.0.0/dist/worker.min.js https://cdn.jsdelivr.net/npm/tesseract.js-core@v7.0.0/ https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng/4.0.0_best_int/"))->toBeTrue()
        ->and(str_contains($adminPolicy, "worker-src 'self' blob: https://cdn.jsdelivr.net/npm/tesseract.js@v7.0.0/dist/worker.min.js"))->toBeTrue()
        ->and(str_contains($adminPolicy, "img-src 'self' data: https://api.qrserver.com blob:"))->toBeTrue();

    $loginPolicy = $this->get('/login')->headers->get('Content-Security-Policy');
    expect(str_contains($loginPolicy, 'cdn.jsdelivr.net'))->toBeFalse()
        ->and(str_contains($loginPolicy, 'blob:'))->toBeFalse()
        ->and(str_contains($loginPolicy, 'www.gstatic.com'))->toBeFalse();
});

it('allows public crawling while excluding private and authentication routes', function () {
    $robots = file_get_contents(public_path('robots.txt'));
    $robotsLines = preg_split('/\R/', trim($robots));

    expect($robotsLines)->toContain('Allow: /')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /ajax/')
        ->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /login')
        ->not->toContain('Disallow: /');
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
