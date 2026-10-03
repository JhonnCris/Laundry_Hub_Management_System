<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => 'SSK Laba Dami | Get notified'])</head>
<body class="staff-body">
<main style="min-height:100vh;display:grid;place-items:center;padding:24px">
    <section class="staff-card" style="max-width:420px;text-align:center">
        <p class="eyebrow">SSK Laba Dami</p>
        <h1 style="font-size:22px;margin-bottom:8px">Get a message when your laundry is ready</h1>
        <p style="color:var(--staff-muted);margin-bottom:18px">Order {{ $code }}. Tap the button and allow notifications. We will notify this device when your order is ready for pickup.</p>
        <button class="save-button" id="enable" type="button">Notify me</button>
        <p id="status" class="save-notice" style="margin-top:12px"></p>
    </section>
</main>
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getMessaging, getToken } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging.js';

    const config = @json($web['firebase']);
    const vapidKey = @json($web['vapidKey']);
    const subscribeUrl = @json($subscribeUrl);
    const status = document.getElementById('status');

    document.getElementById('enable').addEventListener('click', async () => {
        try {
            if (!('serviceWorker' in navigator) || !('Notification' in window)) {
                status.textContent = 'This browser does not support notifications. Please wait for our SMS or email.';
                return;
            }
            if ((await Notification.requestPermission()) !== 'granted') {
                status.textContent = 'Notifications were not allowed.';
                return;
            }
            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            const token = await getToken(getMessaging(initializeApp(config)), { vapidKey, serviceWorkerRegistration: registration });
            const res = await fetch(subscribeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ token }),
            });
            status.textContent = res.ok ? 'All set! We will notify you when your laundry is ready.' : 'Could not save. Please try again.';
        } catch (e) {
            status.textContent = 'Something went wrong. Please try again.';
        }
    });
</script>
</body>
</html>
