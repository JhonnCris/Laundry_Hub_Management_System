<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (HTTP v1) push, no extra package: the service-account JSON is used to
 * sign a short-lived OAuth token. FCM sends *push notifications* to browsers/apps that opted in;
 * it cannot send SMS to a bare phone number.
 */
class FcmService
{
    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Public Firebase web-app settings for the opt-in page (not secret), or null if incomplete.
     *
     * @return array{firebase: array<string, string>, vapidKey: string}|null
     */
    public function webConfig(): ?array
    {
        $web = config('services.fcm.web');
        $projectId = $this->projectId();

        if (! $projectId || blank($web['apiKey'] ?? null) || blank($web['appId'] ?? null)
            || blank($web['messagingSenderId'] ?? null) || blank($web['vapidKey'] ?? null)) {
            return null;
        }

        return [
            'firebase' => [
                'apiKey' => $web['apiKey'],
                'authDomain' => $web['authDomain'] ?: $projectId.'.firebaseapp.com',
                'projectId' => $projectId,
                'messagingSenderId' => $web['messagingSenderId'],
                'appId' => $web['appId'],
            ],
            'vapidKey' => $web['vapidKey'],
        ];
    }

    /** @return 'sent'|'invalid'|'failed' */
    public function send(string $deviceToken, string $title, string $body, string $link): string
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return 'failed';
        }

        try {
            $response = Http::withToken($this->accessToken($credentials))
                ->timeout(8)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => ['title' => $title, 'body' => $body],
                        'webpush' => [
                            // High urgency so a phone in battery-saver / Doze shows it right away; keep for a day.
                            'headers' => ['Urgency' => 'high', 'TTL' => '86400'],
                            'notification' => ['icon' => url('/images/ssk-laba-dami-logo.jpg'), 'requireInteraction' => true],
                            'fcm_options' => ['link' => $link],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('FCM request failed', ['error' => $e->getMessage()]);

            return 'failed';
        }

        if ($response->successful()) {
            return 'sent';
        }

        // The device unsubscribed or the token expired: caller should forget it.
        return in_array($response->json('error.status'), ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true) ? 'invalid' : 'failed';
    }

    private function projectId(): ?string
    {
        return config('services.fcm.project_id') ?: ($this->credentials()['project_id'] ?? null);
    }

    /** @return array<string, string>|null */
    private function credentials(): ?array
    {
        $encoded = config('services.fcm.credentials_base64');
        if (filled($encoded)) {
            $json = json_decode((string) base64_decode($encoded, true), true);

            return is_array($json) && isset($json['client_email'], $json['private_key']) ? $json : null;
        }

        $path = config('services.fcm.credentials');
        if (blank($path)) {
            return null;
        }
        if (! preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) && isset($json['client_email'], $json['private_key']) ? $json : null;
    }

    /** @param  array<string, string>  $credentials */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm:access-token', 3000, function () use ($credentials) {
            $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            return Http::asForm()->timeout(8)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ])->throw()->json('access_token');
        });
    }
}
