<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends SMS through a free option, chosen with SMS_DRIVER in .env:
 *  - android  : your own Android phone as the gateway (free open-source "SMS Gateway for Android"
 *               app, uses your SIM); recommended for Philippine numbers. Works on the phone's local
 *               server (same Wi-Fi) or the app's free Cloud Server (https://api.sms-gate.app/3rdparty/v1/messages),
 *               which is what a hosted site such as Vercel needs.
 *  - textbelt : Textbelt's free tier (1 text/day, mostly US/Canada numbers).
 *  - log      : default; only writes the message to storage/logs (nothing is sent).
 */
class SmsService
{
    public function isLive(): bool
    {
        return in_array(config('services.sms.driver'), ['android', 'textbelt'], true);
    }

    public function send(string $to, string $message): bool
    {
        $number = $this->normalize($to);
        if ($number === null) {
            return false;
        }

        try {
            return match (config('services.sms.driver')) {
                'android' => $this->viaAndroidGateway($number, $message),
                'textbelt' => $this->viaTextbelt($number, $message),
                default => $this->logOnly($number, $message),
            };
        } catch (\Throwable $e) {
            Log::warning('SMS failed', ['driver' => config('services.sms.driver'), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** 09171234567 / 639171234567 / +639171234567 -> +639171234567 */
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (preg_match('/^09\d{9}$/', $digits)) {
            return '+63'.substr($digits, 1);
        }
        if (preg_match('/^63\d{10}$/', $digits)) {
            return '+'.$digits;
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 && str_starts_with(trim($raw), '+') ? '+'.$digits : null;
    }

    private function viaAndroidGateway(string $number, string $message): bool
    {
        $url = config('services.sms.android_url');
        if (blank($url)) {
            return false;
        }

        // The app's Cloud Server (…/3rdparty/v1/messages) takes {textMessage:{text}}; its local server (…/message) takes {message}.
        $body = str_ends_with(rtrim($url, '/'), '/messages')
            ? ['textMessage' => ['text' => $message], 'phoneNumbers' => [$number]]
            : ['message' => $message, 'phoneNumbers' => [$number]];

        return Http::withBasicAuth((string) config('services.sms.android_user'), (string) config('services.sms.android_password'))
            ->timeout(8)
            ->post($url, $body)
            ->successful();
    }

    private function viaTextbelt(string $number, string $message): bool
    {
        $response = Http::asForm()->timeout(8)->post('https://textbelt.com/text', [
            'phone' => $number,
            'message' => $message,
            'key' => config('services.sms.textbelt_key', 'textbelt'),
        ]);

        return (bool) $response->json('success');
    }

    private function logOnly(string $number, string $message): bool
    {
        Log::info('SMS (log driver — nothing sent)', ['to' => $number, 'message' => $message]);

        return true;
    }
}
