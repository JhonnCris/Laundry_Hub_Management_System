<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'SSK Laba Dami') }}</title>

    @vite([
        'resources/css/app.css',
        'resources/css/auth.css',
        'resources/js/app.js'
    ])

    @fluxAppearance
</head>

<body class="auth-body">

    <div class="auth-page">

        <!-- Left Branding Section — logo + wordmark beside the form -->
        <section class="auth-brand">

            <div class="auth-brand-content">

                <a href="{{ url('/') }}" class="auth-logo-link">
                    <img
                        class="auth-logo-img"
                        src="{{ asset('images/ssk-laba-dami-logo.jpg') }}"
                        alt="SSK Laba Dami Laundry Hub logo"
                        width="72"
                        height="72"
                    >
                    <div class="auth-logo-text">
                        <strong>SSK Laba Dami</strong>
                        <small>Laundry Hub</small>
                    </div>
                </a>

                <p class="auth-brand-label">
                    STAFF PORTAL
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
                © {{ date('Y') }} SSK Laba Dami Laundry Hub
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
