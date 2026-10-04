<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head', ['title' => 'SSK Laba Dami | Get notified'])</head>
<body class="staff-body">
<main style="min-height:100vh;display:grid;place-items:center;padding:24px">
    <section class="staff-card" style="max-width:420px;text-align:left">
        <p class="eyebrow">SSK Laba Dami</p>
        <h1 style="font-size:22px;margin-bottom:8px">Get a message when your laundry is ready</h1>
        <p style="color:var(--staff-muted);margin-bottom:18px">Order {{ $code }}. Tap the button and allow notifications. We will notify this device when your order is ready for pickup.</p>
        <button class="save-button" id="enable" type="button">Notify me</button>
        <ul id="steps" style="list-style:none;padding:0;margin:14px 0 0;font-size:13px;display:grid;gap:4px"></ul>
        <button class="outline-action" id="test" type="button" hidden style="margin-top:12px">Send me a test notification</button>
        <p id="status" class="save-notice" style="margin-top:12px"></p>
    </section>
</main>
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getMessaging, getToken, onMessage } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging.js';

    const config = @json($web['firebase']);
    const vapidKey = @json($web['vapidKey']);
    const subscribeUrl = @json($subscribeUrl);
    const testUrl = @json($testUrl);
    const status = document.getElementById('status');
    const steps = document.getElementById('steps');
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    const step = (ok, text) => {
        const li = document.createElement('li');
        li.textContent = (ok ? '✓ ' : '✗ ') + text;
        li.style.color = ok ? '#14553e' : '#af3b2c';
        steps.appendChild(li);
    };

    document.getElementById('enable').addEventListener('click', async () => {
        steps.innerHTML = '';
        status.textContent = '';
        try {
            if (!('serviceWorker' in navigator) || !('Notification' in window)) {
                step(false, 'This browser cannot receive notifications. Open this page in Chrome (Android) or Safari after adding it to the Home Screen (iPhone).');
                return;
            }
            const permission = await Notification.requestPermission();
            step(permission === 'granted', 'Permission: ' + permission);
            if (permission !== 'granted') return;

            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            await navigator.serviceWorker.ready;
            step(!!registration.active, 'Background service: ' + (registration.active ? registration.active.state : 'not active yet'));

            const messaging = getMessaging(initializeApp(config));
            const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });
            step(!!token, 'Phone registered with Google' + (token ? ' (…' + token.slice(-6) + ')' : ''));

            // When this page is open on screen, Google hands the push to the page instead of showing it: show it ourselves.
            onMessage(messaging, (payload) => {
                const n = payload.notification || {};
                registration.showNotification(n.title || 'Your laundry is ready', { body: n.body || '', icon: n.icon || undefined, requireInteraction: true });
            });

            const res = await fetch(subscribeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ token }),
            });
            step(res.ok, 'Saved for this order');
            if (!res.ok) return;

            status.textContent = 'All set! We will notify you when your laundry is ready.';
            // Proof that this phone can show notifications at all.
            await registration.showNotification('Notifications are on', { body: 'You will get a message here when your laundry is ready.' });
            step(true, 'A test notification "Notifications are on" was sent to your phone. Do you see it?');
            document.getElementById('test').hidden = false;
        } catch (e) {
            step(false, 'Error: ' + (e && e.message ? e.message : e));
        }
    });

    document.getElementById('test').addEventListener('click', async () => {
        status.textContent = 'Sending…';
        try {
            const res = await fetch(testUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            const r = await res.json();
            status.textContent = r.result === 'sent'
                ? 'Google accepted the test push. It should appear on your phone in a few seconds (leave this page or lock the screen if it does not).'
                : 'Not delivered: ' + r.detail;
        } catch (e) {
            status.textContent = 'Could not send the test: ' + e.message;
        }
    });
</script>
</body>
</html>
