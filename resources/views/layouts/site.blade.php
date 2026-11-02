<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'SSK Laba Dami Laundry Hub in Matina, Davao City.')">
    <meta name="theme-color" content="#f3f0d4">
    <title>@yield('title', 'SSK Laba Dami | Laundry Hub')</title>
    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/css/auth.css', 'resources/js/app.js'])
</head>
<body class="site-page">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header page-width">
        <a class="site-brand" href="{{ route('home') }}" aria-label="SSK Laba Dami home">
            <img src="{{ asset('images/ssk-laba-dami-logo.jpg') }}" alt="" width="48" height="48">
            <span><strong>SSK Laba Dami</strong><small>Laundry Hub · Matina</small></span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="{{ route('company') }}" @if (request()->routeIs('company')) aria-current="page" @endif>Company</a>
            <a href="{{ route('offerings') }}" @if (request()->routeIs('offerings')) aria-current="page" @endif>Products &amp; services</a>
            <a href="{{ route('journal') }}" @if (request()->routeIs('journal*')) aria-current="page" @endif>Journal</a>
            <a class="nav-cta" href="{{ route('login') }}">Login</a>
        </nav>
    </header>

    <div id="main-content">
        @yield('content')
    </div>

    <footer class="site-footer">
        <div class="page-width footer-main">
            <a class="footer-brand" href="{{ route('home') }}">SSK Laba Dami<span>LOCAL LAUNDRY · EST. 2019</span></a>
            <section class="footer-location" aria-labelledby="footer-location-title">
                <h2 class="footer-label" id="footer-location-title">Visit the shop</h2>
                <address>Door 5, Amicitia Complex<br>Amolo Street–Tahimik Avenue<br>Nabua Subdivision, Matina Crossing<br>Davao City, Philippines 8000</address>
                <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode('Door 5, Amicitia Complex, Amolo Street-Tahimik Avenue, Nabua Subdivision, Matina Crossing, Davao City, Philippines, 8000') }}">Get directions</a>
            </section>
            <section class="footer-contact" aria-labelledby="footer-contact-title">
                <h2 class="footer-label" id="footer-contact-title">Hours &amp; contact</h2>
                <p><strong>Monday–Saturday</strong><br>7:30 am–5:30 pm</p>
                <p><strong>Phone</strong><br><a href="tel:+63943311830">0943 311 1830</a></p>
                <p><strong>Messenger</strong><br>SSK Laba Dami Laundryhub</p>
            </section>
            <nav aria-label="Footer navigation">
                <a href="{{ route('company') }}">Company</a>
                <a href="{{ route('offerings') }}">Products &amp; services</a>
                <a href="{{ route('journal') }}">Journal</a>
            </nav>
        </div>
        <div class="page-width footer-bottom"><span>© {{ date('Y') }} SSK Laba Dami</span><span>Matina, Davao City</span></div>
    </footer>
</body>
</html>
