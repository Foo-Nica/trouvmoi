{{-- Conditions de téléchargement de l'événement : gratuit avec logo, originaux payants, forfait. --}}
@php($fcfa = fn (int $amount) => \App\Models\Order::formatPrice($amount))
@if ($event->isClient() || $event->original_price > 0)
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-lg font-semibold text-ink">
            {{ $event->isClient() ? __('Vos photos en version originale') : __('Version originale sans filigrane') }}
        </h2>
        @if ($event->isClient() && $event->client_name)
            <p class="mt-1 text-sm text-slate-600">{{ __('Séance réalisée pour :name.', ['name' => $event->client_name]) }}</p>
        @endif

        <ul class="mt-3 space-y-2 text-sm text-slate-700">
            @if ($event->allowsFreeDownload())
                <li class="flex gap-2"><span class="text-accent-strong">✓</span> {{ __('Téléchargement gratuit avec le logo TRECHART.') }}</li>
            @endif
            @if ($event->original_price > 0)
                <li class="flex gap-2"><span class="text-accent-strong">✓</span> {{ __('Version originale pleine qualité : :price par photo.', ['price' => $fcfa($event->original_price)]) }}</li>
            @elseif ($event->isClient())
                <li class="flex gap-2"><span class="text-accent-strong">✓</span> {{ __('Versions originales offertes : téléchargez-les depuis chaque photo.') }}</li>
            @endif
            <li class="flex gap-2"><span class="text-accent-strong">✓</span> {{ __('Paiement par Mobile Money ou carte bancaire.') }}</li>
        </ul>

        @if ($pack = $event->packOffer())
            <a href="{{ route('order.checkout', [$event->slug, 'pack' => 1]) }}"
               class="mt-4 flex w-full items-center justify-between gap-3 rounded-xl bg-accent px-4 py-3.5 font-semibold text-ink shadow-sm">
                <span>{{ __('Toutes les photos') }}</span>
                <span class="font-bold">{{ $fcfa($pack) }}</span>
            </a>
        @endif

        @if ($orders->isNotEmpty())
            <div class="mt-5 border-t border-slate-100 pt-4">
                <h3 class="text-sm font-semibold text-slate-700">{{ __('Mes achats') }}</h3>
                <ul class="mt-2 space-y-2">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ $order->privateUrl() }}" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm hover:bg-slate-100">
                                <span class="font-medium text-ink">{{ $order->reference }} · {{ $order->formattedAmount() }}</span>
                                <span @class(['text-xs font-semibold', 'text-emerald-700' => $order->isPaid(), 'text-slate-500' => ! $order->isPaid()])>
                                    {{ $order->isPaid() ? __('Payée') : __('Non payée') }} →
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
