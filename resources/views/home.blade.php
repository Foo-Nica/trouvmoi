<x-layouts.guest :title="__('Vos photos d\'événement, en un instant')" wide>
    <section class="py-8 text-center sm:py-12">
        <img src="{{ asset('images/logo-600.png') }}" alt="{{ config('app.name') }}" class="mx-auto h-14 w-auto sm:h-16">
        <h1 class="mt-6 text-3xl font-bold tracking-tight text-indigo-brand sm:text-4xl">
            {{ __('Scannez. Souriez.') }}<br><span class="text-orange-brand">{{ __('Retrouvez toutes vos photos.') }}</span>
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-slate-600">
            {{ __('Les photographes publient les photos de l\'événement ; chaque invité scanne le code QR, montre son visage une fois et retrouve toutes les photos où il apparaît.') }}
        </p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ route('filament.studio.auth.register') }}" class="w-full rounded-xl bg-orange-brand px-6 py-3.5 font-semibold text-white shadow-sm hover:opacity-90 sm:w-auto">
                {{ __('Je suis photographe') }}
            </a>
            <a href="{{ route('filament.studio.auth.login') }}" class="w-full rounded-xl bg-white px-6 py-3.5 font-semibold text-indigo-brand ring-1 ring-slate-300 hover:bg-slate-50 sm:w-auto">
                {{ __('Se connecter') }}
            </a>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['1', __('Le photographe envoie'), __('Les photos traitées sont publiées et filigranées automatiquement.')],
            ['2', __('L\'invité scanne le QR'), __('La page de l\'événement s\'ouvre, sans application à installer.')],
            ['3', __('Il retrouve ses photos'), __('La reconnaissance faciale rassemble ses photos dans un dossier à télécharger.')],
        ] as [$n, $t, $d])
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-brand text-sm font-semibold text-white">{{ $n }}</div>
                <h2 class="mt-3 font-semibold text-indigo-brand">{{ $t }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $d }}</p>
            </div>
        @endforeach
    </section>

    <section class="mt-12" id="tarifs">
        <h2 class="text-center text-2xl font-bold text-indigo-brand">{{ __('Forfaits photographes') }}</h2>
        <p class="mt-1 text-center text-sm text-slate-600">{{ __('Abonnement mensuel, sans engagement. Gratuit pour les invités.') }}</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (config('trouvmoi.plans') as $key => $plan)
                <div @class(['rounded-2xl bg-white p-5 shadow-sm', 'ring-2 ring-orange-brand' => $key === 'pro', 'ring-1 ring-slate-200' => $key !== 'pro'])>
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-indigo-brand">{{ $key === 'trial' ? __('Essai gratuit') : $plan['label'] }}</h3>
                        @if ($key === 'pro')
                            <span class="rounded-full bg-orange-brand/10 px-2 py-0.5 text-xs font-semibold text-orange-brand">{{ __('Populaire') }}</span>
                        @endif
                    </div>
                    <p class="mt-3">
                        <span class="text-3xl font-bold text-indigo-brand">{{ number_format($plan['price_xof'], 0, ',', ' ') }}</span>
                        <span class="text-sm text-slate-500">{{ __('FCFA / mois') }}</span>
                    </p>
                    <ul class="mt-4 space-y-1.5 text-sm text-slate-600">
                        <li>{{ $plan['events_per_month'] ? trans_choice(':count événement par mois|:count événements par mois', $plan['events_per_month']) : __('Événements illimités') }}</li>
                        <li>{{ __(':count photos par événement', ['count' => number_format($plan['photos_per_event'], 0, ',', ' ')]) }}</li>
                        <li>{{ __('Reconnaissance faciale et QR code') }}</li>
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.guest>
