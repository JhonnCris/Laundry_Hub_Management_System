<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SMS gateway is not yet configured for this project. This logs the
 * outbound message so the "Send SMS" action in the UI is real and
 * traceable, and is the single place to plug in an actual provider
 * (e.g. Twilio, Semaphore, Vonage) when one is available — swap the
 * body of send() for the provider's API call, nothing else changes.
 */
class SmsService
{
    public function send(string $to, string $message): bool
    {
        if (blank($to)) {
            return false;
        }

        Log::info('SMS (stub — no gateway configured)', [
            'to' => $to,
            'message' => $message,
        ]);

        return true;
    }
}
