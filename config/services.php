<?php

return [

    // Firebase Cloud Messaging (web push). Credentials JSON stays out of git (storage/app is ignored).
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS', 'storage/app/firebase-service-account.json'),
        // Base64 of the service-account JSON, for hosts without a writable/persistent disk (Vercel).
        'credentials_base64' => env('FCM_CREDENTIALS_BASE64'),
        'web' => [
            'apiKey' => env('FCM_WEB_API_KEY'),
            'authDomain' => env('FCM_WEB_AUTH_DOMAIN'),
            'messagingSenderId' => env('FCM_WEB_SENDER_ID'),
            'appId' => env('FCM_WEB_APP_ID'),
            'vapidKey' => env('FCM_WEB_VAPID_KEY'),
        ],
    ],

    // Free SMS options (see App\Services\SmsService): log | android | textbelt
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'android_url' => env('SMS_ANDROID_URL'),
        'android_user' => env('SMS_ANDROID_USER'),
        'android_password' => env('SMS_ANDROID_PASSWORD'),
        'textbelt_key' => env('SMS_TEXTBELT_KEY', 'textbelt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
