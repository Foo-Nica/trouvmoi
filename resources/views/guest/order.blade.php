@php($fcfa = fn (int $amount) => \App\Models\Order::formatPrice($amount))
<x-layouts.guest :title="__('Commande :reference', ['reference' => $order->reference])" wide>
    <section class="mb-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-semibold uppercase tracking-wide text-accent-strong">{{ $event->title }}</p>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-ink">{{ __('Commande :reference', ['reference' => $order->reference]) }}</h1>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold',
                'bg-emerald-100 text-emerald-800' => $order->isPaid(),
                'bg-amber-100 text-amber-800' => $order->status === \App\Enums\OrderStatus::Pending,
                'bg-red-100 text-red-800' => $order->status === \App\Enums\OrderStatus::Failed,
            ])>{{ __($order->status->label()) }}</span>
        </div>
        <p class="mt-1 text-sm text-slate-600">
            {{ $order->is_pack ? __('Forfait toutes les photos') : trans_choice(':count photo|:count photos', $photos->count()) }}
            · {{ $order->formattedAmount() }} · {{ $order->customer_name }}
        </p>

        @if ($order->isPaid())
            <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ __('Merci ! Vos photos en version originale sont prêtes. Gardez ce lien pour les retélécharger.') }}
            </p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2" x-data="share(@js($order->privateUrl()), @js(__('Mes photos de :event', ['event' => $event->title])))">
                <a href="{{ route('order.zip', $order->access_token) }}"
                   class="flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-3 font-semibold text-ink shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                    {{ __('Tout télécharger (ZIP)') }}
                </a>
                <button type="button" x-on:click="native"
                        class="flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 font-semibold text-ink ring-1 ring-slate-300">
                    <span x-show="!copied">{{ __('Copier le lien') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Lien copié !') }}</span>
                </button>
            </div>
        @else
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                {{ $order->status === \App\Enums\OrderStatus::Failed
                    ? __('Le paiement n\'a pas abouti. Vous pouvez réessayer.')
                    : __('Paiement en attente. Si vous venez de payer, actualisez cette page dans un instant.') }}
            </p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <form method="POST" action="{{ route('order.retry', $order->access_token) }}">
                    @csrf
                    <button class="w-full rounded-xl bg-accent px-4 py-3 font-semibold text-ink shadow-sm">{{ __('Payer :amount', ['amount' => $order->formattedAmount()]) }}</button>
                </form>
                <a href="{{ route('order.return', $order->access_token) }}" class="flex items-center justify-center rounded-xl bg-white px-4 py-3 font-semibold text-ink ring-1 ring-slate-300">{{ __('Vérifier le paiement') }}</a>
            </div>
        @endif
    </section>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($photos as $photo)
            <figure class="overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                <div class="aspect-square bg-slate-200">
                    <img src="{{ $photo->temporaryUrl('thumb', 120) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                </div>
                @if ($order->isPaid())
                    <figcaption class="px-2 py-1.5">
                        <a href="{{ route('order.download', [$order->access_token, $photo->id]) }}"
                           class="block rounded-md px-2 py-1 text-center text-sm font-semibold text-ink hover:bg-slate-100">{{ __('Télécharger l\'original') }}</a>
                    </figcaption>
                @endif
            </figure>
        @endforeach
    </div>

    <div class="mt-8 text-sm">
        <a href="{{ route('event.show', $event->slug) }}" class="font-medium text-ink">← {{ __('Toutes les photos de l\'événement') }}</a>
    </div>
</x-layouts.guest>
