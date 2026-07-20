<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <link rel="icon" type="image/x-icon" href="/favicon.ico" />
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <link rel="manifest" href="/site.webmanifest" />
        <meta name="theme-color" content="#2b2c30" />

        <title inertia>SimpleVoter</title>

        {{-- Open Graph (WhatsApp, Facebook, LinkedIn, ...) --}}
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="SimpleVoter" />
        <meta property="og:title" content="SimpleVoter – Umfragen in Sekunden erstellt" />
        <meta property="og:description" content="Kostenlos, ohne Anmeldung. Frage stellen, Link teilen, live abstimmen." />
        <meta property="og:image" content="{{ url('/images/og-image.png') }}" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:url" content="{{ url()->current() }}" />

        {{-- Twitter/X Card --}}
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="SimpleVoter – Umfragen in Sekunden erstellt" />
        <meta name="twitter:description" content="Kostenlos, ohne Anmeldung. Frage stellen, Link teilen, live abstimmen." />
        <meta name="twitter:image" content="{{ url('/images/og-image.png') }}" />

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-body antialiased">
        @inertia
    </body>
</html>
