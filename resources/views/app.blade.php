<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0f0e">
        <meta name="application-name" content="FinanSys">
        <meta name="apple-mobile-web-app-title" content="FinanSys">
        <link rel="icon" type="image/svg+xml" href="{{ asset('finansys-icon.svg') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <title inertia>{{ config('app.name', 'FinanSys') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
