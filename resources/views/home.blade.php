<x-layouts.guest :title="__('Vos photos d\'événement')" wide>
    <section class="relative overflow-hidden rounded-3xl bg-ink px-6 py-12 text-white sm:px-12 sm:py-16">
        <div class="bg-pattern-white pointer-events-none absolute inset-0 opacity-[.06]" aria-hidden="true"></div>
        <div class="relative max-w-2xl">
            <img src="{{ asset('images/mark-white.png') }}" alt="" class="h-14 w-14">
            <h1 class="mt-6 text-3xl font-bold leading-tight tracking-tight sm:text-5xl">
                {{ __('Chaque image mérite') }}<br><span class="text-accent">{{ __('un regard d\'expert.') }}</span>
            </h1>
            <p class="mt-4 max-w-xl text-white/75">
                {{ __('Vous avez été photographié par TRECHART lors d\'un événement ? Scannez le code QR remis sur place, ou entrez le code de l\'événement : montrez votre visage une fois et retrouvez toutes vos photos.') }}
            </p>

            <form method="GET" action="{{ route('event.find') }}" class="mt-8 flex max-w-md flex-col gap-3 sm:flex-row">
                <label for="code" class="sr-only">{{ __('Code de l\'événement') }}</label>
                <input id="code" name="code" required maxlength="16" autocomplete="off" autocapitalize="none"
                       value="{{ old('code') }}"
                       placeholder="{{ __('Code de l\'événement') }}"
                       class="min-w-0 flex-1 rounded-xl border-0 bg-white px-4 py-3.5 text-ink placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-accent/40">
                <button class="rounded-xl bg-accent px-6 py-3.5 font-semibold text-ink shadow-sm hover:brightness-105">
                    {{ __('Voir mes photos') }}
                </button>
            </form>
            <p class="mt-3 text-xs text-white/50">{{ __('Le code figure sous le QR code de l\'événement.') }}</p>
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-center text-2xl font-bold text-ink">{{ __('Vos photos en trois gestes') }}</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['1', __('Scannez le QR code'), __('La page de l\'événement s\'ouvre sur votre téléphone, sans application à installer.')],
                ['2', __('Montrez votre visage'), __('Touchez votre visage sur une photo ou prenez un selfie : la reconnaissance faciale fait le reste.')],
                ['3', __('Téléchargez vos photos'), __('Toutes vos photos sont rassemblées dans un dossier personnel, à garder et à partager.')],
            ] as [$n, $t, $d])
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-sm font-bold text-ink">{{ $n }}</div>
                    <h3 class="mt-3 font-semibold text-ink">{{ $t }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-12 grid gap-8 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10 lg:grid-cols-[1fr_2fr]">
        <div>
            <img src="{{ asset('images/logo-dark.png') }}" alt="{{ config('app.name') }}" class="h-12 w-auto">
            <p class="mt-4 text-sm font-semibold uppercase tracking-wide text-accent-strong">{{ __('Studio créatif et de production visuelle') }}</p>
        </div>
        <div class="grid gap-6 sm:grid-cols-3">
            @foreach ([
                [__('Qui sommes-nous'), __('TRECHART transforme les idées en expériences visuelles fortes, mémorables et porteuses de sens.')],
                [__('Notre vision'), __('Devenir une référence créative dans la conception d\'univers visuels qui donnent une identité forte aux idées, aux marques et aux projets.')],
                [__('Notre mission'), __('Transformer les visions en expériences visuelles puissantes, de la réflexion stratégique à la réalisation.')],
            ] as [$t, $d])
                <div class="border-l-4 border-accent pl-4">
                    <h3 class="font-semibold text-ink">{{ $t }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.guest>
