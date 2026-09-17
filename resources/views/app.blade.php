<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0f0e">
        <meta name="application-name" content="FinanSys">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ route('brand.favicon') }}?v=2">

        @if (request()->routeIs('home'))
            <meta name="description" content="Organize contas, caixinhas e movimentações em um só lugar com o FinanSys.">
            <link rel="canonical" href="{{ route('home') }}">
            <meta property="og:type" content="website">
            <meta property="og:locale" content="pt_BR">
            <meta property="og:site_name" content="FinanSys">
            <meta property="og:title" content="FinanSys — Controle hoje, mais amanhã">
            <meta property="og:description" content="Organize contas, caixinhas e movimentações em um só lugar com o FinanSys.">
            <meta property="og:url" content="{{ route('home') }}">
            <meta property="og:image" content="{{ route('brand.social') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:type" content="image/png">
            <meta property="og:image:alt" content="FinanSys — controle hoje, mais amanhã">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="FinanSys — Controle hoje, mais amanhã">
            <meta name="twitter:description" content="Organize suas finanças pessoais com o FinanSys.">
            <meta name="twitter:image" content="{{ route('brand.social') }}">
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
