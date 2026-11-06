<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2B2A6B">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="bg-indigo-brand text-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">
                trouv<span class="text-orange-brand">moi</span>
            </a>
            {{ $header ?? '' }}
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-8 pt-4 text-center text-xs text-slate-500">
        {{ config('app.name') }} · Retrouvez vos photos d'événement en un instant
    </footer>
</body>
</html>
