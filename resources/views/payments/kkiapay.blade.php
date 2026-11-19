<x-layouts.guest :title="__('Paiement')">
    <section class="mx-auto max-w-md rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
        <h1 class="text-xl font-bold text-ink">{{ __('Commande :reference', ['reference' => $order->reference]) }}</h1>
        <p class="mt-1 text-3xl font-bold text-ink">{{ $order->formattedAmount() }}</p>
        <p class="mt-2 text-sm text-slate-600">{{ __('La fenêtre de paiement KKiaPay s\'ouvre. Payez par Mobile Money ou carte bancaire.') }}</p>

        <button type="button" id="kkiapay-open" class="mt-6 w-full rounded-xl bg-accent px-5 py-3.5 font-bold text-ink shadow-sm">
            {{ __('Payer :amount', ['amount' => $order->formattedAmount()]) }}
        </button>
        <a href="{{ $order->privateUrl() }}" class="mt-3 block text-sm text-slate-500 underline">{{ __('Annuler') }}</a>
    </section>

    <script src="{{ $widgetUrl }}"></script>
    <script>
        (function () {
            var returnUrl = @js($returnUrl);
            function open() {
                openKkiapayWidget({
                    amount: @js($order->amount),
                    key: @js($publicKey),
                    sandbox: @js($sandbox),
                    position: 'center',
                    theme: '#ED8B00',
                    name: @js($order->customer_name),
                    phone: @js(preg_replace('/\D/', '', $order->customer_phone)),
                    email: @js($order->customer_email),
                    data: @js($order->reference),
                });
            }
            addSuccessListener(function (response) {
                window.location = returnUrl + (returnUrl.indexOf('?') === -1 ? '?' : '&') + 'transaction_id=' + encodeURIComponent(response.transactionId);
            });
            document.getElementById('kkiapay-open').addEventListener('click', open);
            open();
        })();
    </script>
</x-layouts.guest>
