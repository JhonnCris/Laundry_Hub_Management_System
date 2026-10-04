<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $title ?? 'Laravel' }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<link rel="icon" href="{{ asset('images/ssk-laba-dami-logo.jpg') }}">

<script>
    (function () {
        try {
            var theme = localStorage.getItem('ssk-theme');
            var textSize = localStorage.getItem('ssk-text-size');
            if (theme) document.documentElement.setAttribute('data-theme', theme);
            if (textSize && textSize !== 'normal') document.documentElement.setAttribute('data-text-size', textSize);
        } catch (e) {}
    })();
</script>
