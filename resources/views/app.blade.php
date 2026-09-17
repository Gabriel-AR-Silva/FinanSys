<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'FinanSys') }}</title>
        <meta name="description" content="FinanSys — organização financeira pessoal com visão clara de contas, lançamentos, cartões e planejamento." />
        <meta name="theme-color" content="#020617" />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="FinanSys" />
        <meta property="og:title" content="FinanSys" />
        <meta property="og:description" content="Organize suas finanças pessoais com mais clareza e controle." />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:image" content="{{ asset('images/tsuki/tsuki.png') }}" />
        <meta property="og:image:alt" content="Tsuki, assistente visual do FinanSys" />

        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" content="FinanSys" />
        <meta name="twitter:description" content="Organize suas finanças pessoais com mais clareza e controle." />
        <meta name="twitter:image" content="{{ asset('images/tsuki/tsuki.png') }}" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
