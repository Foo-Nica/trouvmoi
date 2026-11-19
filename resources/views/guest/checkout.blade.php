@php($fcfa = fn (int $amount) => \App\Models\Order::formatPrice($amount))
@php($driver = config('trouvmoi.payments.driver'))
<x-layouts.guest :title="__('Votre commande')">
    <div class="space-y-5">
        <a href="{{ route('event.show', $event->slug) }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink">← {{ __('Retour à la galerie') }}</a>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-accent-strong">{{ $event->title }}</p>
            <h1 class="mt-1 text-2xl font-bold text-ink">{{ __('Votre commande') }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $pack
                    ? __('Forfait : toutes les photos de la séance, en version originale.')
                    : trans_choice(':count photo en version originale, pleine qualité, sans filigrane.|:count photos en version originale, pleine qualité, sans filigrane.', $photos->count()) }}
            </p>

            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach ($photos->take($pack ? 8 : 200) as $photo)
                    <figure class="overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                        <img src="{{ $photo->temporaryUrl('thumb', 60) }}" alt="" class="aspect-square w-full object-cover">
                        @unless ($pack)
                            <figcaption class="flex items-center justify-between gap-1 px-2 py-1 text-xs">
                                <span class="font-semibold text-ink">{{ $fcfa($event->priceFor($photo)) }}</span>
                                @if (! $folder)
                                    <form method="POST" action="{{ route('cart.remove', [$event->slug, $photo->id]) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-slate-400 hover:text-red-600" aria-label="{{ __('Retirer du panier') }}">✕</button>
                                    </form>
                                @endif
                            </figcaption>
                        @endunless
                    </figure>
                @endforeach
            </div>
            @if ($pack && $photos->count() > 8)
                <p class="mt-2 text-sm text-slate-500">{{ __('Et :count autres photos.', ['count' => $photos->count() - 8]) }}</p>
            @endif

            <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                <span class="font-semibold text-ink">{{ __('Total') }}</span>
                <span class="text-2xl font-bold text-ink">{{ $fcfa($total) }}</span>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold text-ink">{{ __('Vos coordonnées') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Elles servent uniquement au paiement et à l\'envoi du lien de vos photos.') }}</p>

            <form method="POST" action="{{ route('order.store', $event->slug) }}" class="mt-4 space-y-3">
                @csrf
                @if ($pack) <input type="hidden" name="pack" value="1"> @endif
                @if ($folder) <input type="hidden" name="folder" value="{{ $folder->access_token }}"> @endif

                <label class="block text-sm font-medium text-slate-700">
                    {{ __('Nom complet') }}
                    <input name="customer_name" value="{{ old('customer_name', $event->isClient() ? $event->client_name : '') }}" required maxlength="120" autocomplete="name"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-accent focus:ring-accent">
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    {{ __('Téléphone (Mobile Money)') }}
                    <input name="customer_phone" value="{{ old('customer_phone') }}" required maxlength="30" inputmode="tel" autocomplete="tel" placeholder="01 97 00 00 00"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-accent focus:ring-accent">
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    {{ __('E-mail (facultatif, pour recevoir le lien)') }}
                    <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="150" autocomplete="email"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-accent focus:ring-accent">
                </label>

                <button class="mt-2 w-full rounded-xl bg-accent px-5 py-4 text-base font-bold text-ink shadow-sm active:scale-[.99]">
                    {{ __('Payer :amount', ['amount' => $fcfa($total)]) }}
                </button>
                <p class="text-center text-xs text-slate-500">
                    @if ($demo)
                        {{ __('Mode démonstration : aucun argent ne sera débité.') }}
                    @else
                        {{ __('Paiement sécurisé par :provider (Mobile Money, carte bancaire).', ['provider' => $driver === 'fedapay' ? 'FedaPay' : 'KKiaPay']) }}
                    @endif
                </p>
            </form>
        </section>
    </div>
</x-layouts.guest>
