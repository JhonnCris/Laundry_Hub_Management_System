<?php

use App\Mail\LaundryReadyMail;
use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function processingOrder(array $customer): LaundryTransaction
{
    $c = Customer::create($customer);

    return LaundryTransaction::create([
        'customer_id' => $c->id, 'transaction_type' => 'drop_off', 'status' => 'processing',
        'payment_status' => 'paid', 'subtotal' => 50, 'total_amount' => 50,
    ]);
}

function markReady(LaundryTransaction $order)
{
    return test()->actingAs(User::factory()->create(['role' => 'staff']))
        ->patchJson("/api/staff/transactions/{$order->id}/status", ['status' => 'ready_for_pickup']);
}

it('emails and texts the customer when the order is ready for pickup', function () {
    Mail::fake();
    config(['mail.default' => 'smtp', 'services.sms.driver' => 'android', 'services.sms.android_url' => 'http://phone.local:8080/message']);
    Http::fake(['phone.local:8080/*' => Http::response(['id' => 1], 202)]);
    $order = processingOrder(['name' => 'Ana', 'contact_number' => '09171234567', 'email' => 'ana@example.com']);

    markReady($order)->assertOk()->assertJsonPath('notify.email', 'sent')->assertJsonPath('notify.sms', 'sent');

    Mail::assertSent(LaundryReadyMail::class, fn ($m) => $m->hasTo('ana@example.com'));
    Http::assertSent(fn ($r) => $r['phoneNumbers'] === ['+639171234567'] && str_contains($r['message'], 'ready for pickup'));
    expect($order->fresh()->email_sent_at)->not->toBeNull()->and($order->fresh()->sms_sent_at)->not->toBeNull();
});

it('skips channels the customer has no contact for and does not resend', function () {
    Mail::fake();
    config(['mail.default' => 'smtp']);
    $order = processingOrder(['name' => 'No Contact']);

    markReady($order)->assertOk()->assertJsonPath('notify.email', 'skipped')->assertJsonPath('notify.sms', 'skipped');
    Mail::assertNothingSent();
});

it('still changes the status when email delivery fails', function () {
    config(['mail.default' => 'smtp']);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    $order = processingOrder(['name' => 'Ana', 'email' => 'ana@example.com']);

    markReady($order)->assertOk()->assertJsonPath('notify.email', 'failed');
    expect($order->fresh()->status)->toBe('ready_for_pickup');
});

it('normalizes Philippine mobile numbers', function () {
    $sms = new SmsService;

    expect($sms->normalize('0917 123 4567'))->toBe('+639171234567')
        ->and($sms->normalize('639171234567'))->toBe('+639171234567')
        ->and($sms->normalize('12345'))->toBeNull();
});
