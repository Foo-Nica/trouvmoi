<x-layouts.guest :title="$event->title">
    <div class="space-y-5">
        @include('guest.partials.event-header')
        <form method="POST" action="{{ route('event.pin', $event->slug) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @csrf
            <label for="pin" class="text-lg font-semibold text-ink">{{ __('Code d\'accès') }}</label>
            <p class="mt-1 text-sm text-slate-600">{{ __('Saisissez le code communiqué par l\'organisateur avec le QR code.') }}</p>
            <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="one-time-code" required autofocus
                   class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-2xl tracking-[.5em] focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30">
            <button class="mt-4 w-full rounded-xl bg-accent px-5 py-3.5 font-semibold text-ink shadow-sm">{{ __('Entrer') }}</button>
        </form>
    </div>
</x-layouts.guest>
