{{-- Barre du panier, fixée en bas de l'écran dès qu'une photo y est ajoutée. --}}
@php($cart = app(\App\Services\Orders\Cart::class))
@php($cartPhotos = $cart->photos($event))
@if ($cartPhotos->isNotEmpty())
    <div class="h-20"></div>
    <div class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-accent bg-ink px-4 py-3 text-white shadow-2xl" style="padding-bottom: max(.75rem, env(safe-area-inset-bottom))">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold">{{ trans_choice(':count photo dans le panier|:count photos dans le panier', $cartPhotos->count()) }}</p>
                <p class="text-xs text-white/70">{{ __('Version originale, sans filigrane') }}</p>
            </div>
            <a href="{{ route('order.checkout', $event->slug) }}"
               class="shrink-0 rounded-xl bg-accent px-5 py-3 text-sm font-bold text-ink shadow-sm">
                {{ __('Payer :amount', ['amount' => \App\Models\Order::formatPrice($cart->total($event))]) }}
            </a>
        </div>
    </div>
@endif
