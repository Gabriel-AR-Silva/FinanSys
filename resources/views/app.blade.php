<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0f0e">
        <meta name="application-name" content="FinanSys">
        <meta name="apple-mobile-web-app-title" content="FinanSys">
        <link rel="icon" type="image/svg+xml" href="{{ asset('finansys-icon.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('finansys-32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        @if (request()->routeIs('home'))
            <meta name="description" content="Organize suas contas, caixinhas e movimentações em um só lugar com o FinanSys.">
            <link rel="canonical" href="{{ route('home') }}">
            <meta property="og:type" content="website">
            <meta property="og:locale" content="pt_BR">
            <meta property="og:site_name" content="FinanSys">
            <meta property="og:title" content="FinanSys — Sua vida financeira, visível de verdade">
            <meta property="og:description" content="Organize suas contas, caixinhas e movimentações em um só lugar com o FinanSys.">
            <meta property="og:url" content="{{ route('home') }}">
            <meta property="og:image" content="{{ asset('finansys-social.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:type" content="image/png">
            <meta property="og:image:alt" content="FinanSys: sua vida financeira, visível de verdade.">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="FinanSys — Sua vida financeira, visível de verdade">
            <meta name="twitter:description" content="Organize suas contas, caixinhas e movimentações em um só lugar com o FinanSys.">
            <meta name="twitter:image" content="{{ asset('finansys-social.png') }}">
        @endif
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
