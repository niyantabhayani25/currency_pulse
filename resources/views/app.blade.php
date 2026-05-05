<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'CurrencyPulse') }}</title>

        <!-- Preconnect for Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <!-- CSRF (needed for Inertia PUT/POST/DELETE) -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Vite assets -->
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="antialiased bg-[#0a0c10]">
        @inertia
    </body>
</html>
