<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laundry Management System</title>

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/css/auth.css', 'resources/js/app.js'])
</head>

<body>

    <header class="welcome-header">
        <nav class="welcome-nav">
            @auth
                <a href="{{ url('/dashboard') }}" class="nav-link">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="nav-link">
                    Log in
                </a>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="nav-button">
                        Register
                    </a>
                @endif
            @endauth
        </nav>
    </header>

    <main class="welcome-main">
        <div class="welcome-content">

            <p class="welcome-label">LAUNDRY MANAGEMENT SYSTEM</p>

            <h1>
                Manage your laundry business
                <span>with ease.</span>
            </h1>

            <p class="welcome-description">
                A simple and organized system for managing customers,
                laundry orders, payments, and daily operations.
            </p>

            @guest
                <div class="welcome-actions">
                    <a href="{{ route('login') }}" class="primary-button">
                        Get Started
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="secondary-button">
                            Create Account
                        </a>
                    @endif
                </div>
            @endguest

        </div>
    </main>

</body>
</html>