<x-layouts.guest :title="$event->title">
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <img src="{{ asset('images/mark-512.png') }}" alt="" class="mx-auto h-14 w-14">
        <h1 class="mt-4 text-xl font-semibold text-indigo-brand">{{ $event->title }}</h1>
        <p class="mt-2 text-slate-600">
            @if ($event->isExpired())
                {{ __('Cet événement est terminé : ses photos ne sont plus disponibles.') }}
            @else
                {{ __('Les photos de cet événement ne sont pas encore en ligne. Revenez un peu plus tard !') }}
            @endif
        </p>
    </div>
</x-layouts.guest>
