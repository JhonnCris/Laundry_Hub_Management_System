<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laundry Management System') }}</title>

    @vite([
        'resources/css/app.css',
        'resources/css/auth.css',
        'resources/js/app.js'
    ])

    @fluxAppearance
</head>

<body class="auth-body">

    <div class="auth-page">

        <!-- Left Branding Section -->
        <section class="auth-brand">

            <div class="auth-brand-content">

                <div class="auth-logo">
                    LMS
                </div>

                <p class="auth-brand-label">
                    LAUNDRY MANAGEMENT SYSTEM
                </p>

                <h1>
                    Manage your laundry business with ease.
                </h1>

                <p class="auth-brand-description">
                    Keep your customers, laundry orders, payments,
                    and daily operations organized in one simple system.
                </p>

            </div>

            <div class="auth-brand-footer">
                © {{ date('Y') }} Laundry Management System
            </div>

        </section>


        <!-- Form Section -->
        <main class="auth-form-section">

            <div class="auth-form-container">

                {{ $slot }}

            </div>

        </main>

    </div>

</body>
</html>