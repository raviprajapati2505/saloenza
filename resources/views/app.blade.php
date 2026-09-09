<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#E0229A">
        <meta name="application-name" content="Glowsuite">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Glowsuite">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="format-detection" content="telephone=no">
        <link rel="manifest" href="/manifest.json">
        <script>
            window.__pwaDeferredInstallPrompt = null;
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                window.__pwaDeferredInstallPrompt = e;
                window.dispatchEvent(new Event('pwa-install-available'));
            });
        </script>
        <link rel="icon" href="/icons/favicon-32x32.png" sizes="32x32" type="image/png">
        <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512x512.png">
        <link rel="apple-touch-icon" href="/icons/icon-512x512.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png">
        <meta name="msapplication-TileColor" content="#000000">
        <meta name="msapplication-TileImage" content="/icons/icon-512x512.png">
        <title>{{ config('app.name', 'Glowsuite') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/main.jsx'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
