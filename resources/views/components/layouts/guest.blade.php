@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#333333">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:image" content="{{ asset('images/icon-512.png') }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-neutral-50 text-ink antialiased">
    <header class="sticky top-0 z-30 border-b-2 border-accent bg-ink text-white shadow-sm">
        <div @class(['mx-auto flex items-center justify-between gap-3 px-4 py-3', 'max-w-5xl' => $wide, 'max-w-3xl' => ! $wide])>
            <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('app.name') }}">
                <img src="{{ asset('images/logo-white.png') }}" alt="{{ config('app.name') }}" class="h-9 w-auto">
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

    <main @class(['mx-auto w-full flex-1 px-4 py-5', 'max-w-5xl' => $wide, 'max-w-3xl' => ! $wide])>
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

    <footer class="mt-6 border-t-4 border-accent bg-ink text-white/70">
        <div class="mx-auto max-w-5xl px-4 py-8 text-center text-xs">
            <img src="{{ asset('images/logo-white.png') }}" alt="{{ config('app.name') }}" class="mx-auto mb-3 h-7 w-auto">
            <p>{{ __('Chaque image mérite un regard d\'expert.') }}</p>
            <nav class="mt-4 flex flex-wrap justify-center gap-x-5 gap-y-2">
                <a href="{{ route('legal.privacy') }}" class="hover:text-white">{{ __('Confidentialité') }}</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-white">{{ __('Conditions d\'utilisation') }}</a>
                <a href="{{ route('filament.studio.auth.login') }}" class="hover:text-white">{{ __('Espace équipe') }}</a>
            </nav>
            <p class="mt-4 text-white/40">&copy; {{ now()->year }} {{ config('app.name') }}</p>
        </div>
    </footer>
</body>
</html>
