<x-layouts.guest :title="__('Paiement')">
    <section class="mx-auto max-w-md rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">
            {{ __('Mode démonstration : aucun argent ne sera débité.') }}
        </p>
        <h1 class="mt-5 text-xl font-bold text-ink">{{ __('Commande :reference', ['reference' => $order->reference]) }}</h1>
        <p class="mt-1 text-3xl font-bold text-ink">{{ $order->formattedAmount() }}</p>
        <p class="mt-2 text-sm text-slate-600">{{ $order->customer_name }} · {{ $order->customer_phone }}</p>

        <form method="POST" action="{{ route('order.simulate.submit', $order->access_token) }}" class="mt-6 space-y-2">
            @csrf
            <button name="result" value="paid" class="w-full rounded-xl bg-accent px-5 py-3.5 font-bold text-ink shadow-sm">{{ __('Simuler un paiement réussi') }}</button>
            <button name="result" value="failed" class="w-full rounded-xl bg-white px-5 py-3 font-semibold text-slate-600 ring-1 ring-slate-300">{{ __('Simuler un échec') }}</button>
        </form>
        <p class="mt-4 text-xs text-slate-500">{{ __('En production, cette étape est remplacée par la page de paiement KKiaPay ou FedaPay.') }}</p>
    </section>
</x-layouts.guest>
