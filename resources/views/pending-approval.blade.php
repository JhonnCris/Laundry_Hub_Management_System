<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => 'SSK Laba Dami | Awaiting approval'])</head>
<body class="staff-body">
<main style="min-height:100vh;display:grid;place-items:center;padding:24px">
    <section class="staff-card" style="max-width:440px;text-align:center">
        <p class="eyebrow">Account created</p>
        <h1 style="font-size:24px;margin-bottom:10px">Awaiting admin approval</h1>
        <p style="color:var(--staff-muted);margin-bottom:18px">Hi {{ auth()->user()->name }}, your account is not active yet. Ask the owner or an admin to approve it in <strong>Manage Users</strong>, then sign in again.</p>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="save-button" type="submit">Log out</button>
        </form>
    </section>
</main>
</body>
</html>
