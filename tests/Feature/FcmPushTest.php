<?php

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function fcmConfigured(): void
{
    // Windows PHP needs an openssl.cnf to generate a throwaway test key.
    $options = ['private_key_bits' => 2048];
    foreach (['extras/ssl/openssl.cnf', 'extras/openssl/openssl.cnf'] as $relative) {
        if (! getenv('OPENSSL_CONF') && is_file(dirname(PHP_BINARY).'/'.$relative)) {
            $options['config'] = dirname(PHP_BINARY).'/'.$relative;
        }
    }
    $key = openssl_pkey_new($options);
    openssl_pkey_export($key, $privateKey, null, $options);
    $path = sys_get_temp_dir().'/fcm-test-'.uniqid().'.json';
    file_put_contents($path, json_encode([
        'project_id' => 'demo-proj', 'client_email' => 'svc@demo-proj.iam.gserviceaccount.com',
        'private_key' => $privateKey, 'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config([
        'services.fcm.credentials' => $path,
        'services.fcm.web' => ['apiKey' => 'k', 'authDomain' => null, 'messagingSenderId' => '1', 'appId' => 'a', 'vapidKey' => 'v'],
    ]);
    Cache::forget('fcm:access-token');
}

function fcmOrder(): LaundryTransaction
{
    $customer = Customer::create(['name' => 'Push Customer', 'contact_number' => '0900']);

    return LaundryTransaction::create([
        'customer_id' => $customer->id, 'transaction_type' => 'drop_off', 'status' => 'processing',
        'payment_status' => 'paid', 'subtotal' => 10, 'total_amount' => 10,
    ]);
}

it('requires a valid signature for the customer opt-in page and stores the device token', function () {
    fcmConfigured();
    $order = fcmOrder();

    $this->get("/notify/{$order->id}")->assertForbidden();

    $url = URL::temporarySignedRoute('notify.show', now()->addDay(), ['transaction' => $order->id]);
    $this->get($url)->assertOk()->assertSee('Notify me');
    $this->postJson($url, ['token' => 'device-token-123'])->assertOk();

    expect(Cache::get("fcm:order:{$order->id}"))->toBe('device-token-123');
});

it('sends a push to the opted-in device when the order is ready', function () {
    fcmConfigured();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/demo-proj/messages/1'], 200),
    ]);
    $order = fcmOrder();
    Cache::put("fcm:order:{$order->id}", 'device-token-123', now()->addDay());

    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'ready_for_pickup'])
        ->assertOk()
        ->assertJsonPath('notify.push', 'sent');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'projects/demo-proj/messages:send')
        && $r['message']['token'] === 'device-token-123'
        && $r['message']['webpush']['headers']['Urgency'] === 'high');
    expect(Cache::get("fcm:order:{$order->id}"))->toBeNull();
});

it('does nothing for push when the customer never opted in', function () {
    fcmConfigured();
    $order = fcmOrder();

    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->patchJson("/ajax/staff/transactions/{$order->id}/status", ['status' => 'ready_for_pickup'])
        ->assertJsonPath('notify.push', 'skipped');
});

it('serves the service worker only when Firebase web settings exist', function () {
    $this->get('/firebase-messaging-sw.js')->assertNotFound();

    fcmConfigured();
    $this->get('/firebase-messaging-sw.js')->assertOk()->assertSee('firebase.initializeApp', false);
});

it('reads the Firebase key from a base64 environment value when no file exists', function () {
    fcmConfigured();
    $json = file_get_contents(config('services.fcm.credentials'));
    config([
        'services.fcm.credentials' => 'storage/app/does-not-exist.json',
        'services.fcm.credentials_base64' => base64_encode($json),
    ]);

    expect(app(FcmService::class)->isConfigured())->toBeTrue();

    config(['services.fcm.credentials_base64' => base64_encode('not json')]);
    expect(app(FcmService::class)->isConfigured())->toBeFalse();
});
