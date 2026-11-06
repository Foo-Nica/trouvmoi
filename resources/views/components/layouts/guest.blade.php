@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#282667">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:image" content="{{ asset('images/icon-512.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="sticky top-0 z-30 bg-indigo-brand text-white shadow-sm">
        <div @class(['mx-auto flex items-center justify-between gap-3 px-4 py-3', 'max-w-5xl' => $wide, 'max-w-3xl' => ! $wide])>
            <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('app.name') }}">
                <img src="{{ asset('images/logo-white-600.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
            </a>
            <nav class="flex items-center gap-1 text-xs font-medium" aria-label="{{ __('Langue') }}">
                @foreach (config('trouvmoi.locales') as $code => $label)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                       title="{{ $label }}"
                       @class(['rounded-md px-2 py-1 uppercase', 'bg-white/20' => app()->getLocale() === $code, 'text-white/70 hover:text-white' => app()->getLocale() !== $code])>{{ $code }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main @class(['mx-auto px-4 py-5', 'max-w-5xl' => $wide, 'max-w-3xl' => ! $wide])>
        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-10 pt-4 text-center text-xs text-slate-500">
        <img src="{{ asset('images/logo-600.png') }}" alt="" class="mx-auto mb-2 h-5 w-auto opacity-70">
        {{ __('Retrouvez vos photos d\'événement en un instant') }}
    </footer>
</body>
</html>
