<x-layouts.guest title="Vos photos d'événement, en un instant">
    <section class="py-10 text-center">
        <h1 class="text-3xl font-bold tracking-tight text-indigo-brand sm:text-4xl">
            Scannez. Souriez.<br><span class="text-orange-brand">Retrouvez toutes vos photos.</span>
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-slate-600">
            Les photographes publient les photos de l'événement ; chaque invité scanne le code QR,
            montre son visage une fois et retrouve toutes les photos où il apparaît.
        </p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ route('filament.studio.auth.register') }}" class="rounded-xl bg-orange-brand px-6 py-3 font-semibold text-white shadow-sm hover:opacity-90">
                Je suis photographe
            </a>
            <a href="{{ route('filament.studio.auth.login') }}" class="rounded-xl border border-slate-300 px-6 py-3 font-semibold text-slate-700 hover:bg-white">
                Se connecter
            </a>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['1', 'Le photographe envoie', 'Les photos traitées sont publiées et filigranées automatiquement.'],
            ['2', "L'invité scanne le QR", 'La page de l’événement s’ouvre, sans application à installer.'],
            ['3', 'Il retrouve ses photos', 'La reconnaissance faciale rassemble ses photos dans un dossier à télécharger.'],
        ] as [$n, $t, $d])
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-brand text-sm font-semibold text-white">{{ $n }}</div>
                <h2 class="mt-3 font-semibold">{{ $t }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $d }}</p>
            </div>
        @endforeach
    </section>
</x-layouts.guest>
