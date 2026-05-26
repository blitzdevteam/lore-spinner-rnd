<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Marcellus+SC&display=swap" rel="stylesheet">

        @php
            $pageComponent = $page['component'];
            $pageEntry = str_starts_with($pageComponent, 'VoiceLab/')
                ? 'resources/js/voice-lab/pages/' . substr($pageComponent, strlen('VoiceLab/')) . '.vue'
                : 'resources/js/pages/' . $pageComponent . '.vue';
        @endphp
        @vite(['resources/js/app.ts', $pageEntry])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
