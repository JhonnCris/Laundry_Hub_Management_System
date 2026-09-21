<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-screen">
        <main class="auth-page">
            <section class="auth-brand">
                <a href="{{ route('home') }}" class="auth-brand-logo" wire:navigate><span>SSK</span><div><strong>SSK Laba Dami</strong><small>Laundry Hub</small></div></a>
                <div class="auth-brand-copy"><p class="auth-brand-label">STAFF PORTAL</p><h1>Less time tracking.<br>More time caring.</h1><p>Manage laundry transactions, machines, inventory, and customer updates from one clear workspace.</p></div>
                <div class="auth-brand-footer"><span>●</span> Simple tools for a smoother laundry day.</div>
            </section>
            <section class="auth-form-section"><div class="auth-form-container"><a href="{{ route('home') }}" class="auth-mobile-brand" wire:navigate><span>SSK</span> SSK Laba Dami</a>{{ $slot }}</div></section>
        </main>
        @fluxScripts
    </body>
</html>
