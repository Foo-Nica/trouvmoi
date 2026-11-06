<x-layouts.guest :title="$event->title">
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <h1 class="text-xl font-semibold text-indigo-brand">{{ $event->title }}</h1>
        <p class="mt-2 text-slate-600">
            @if ($event->isExpired())
                Cet événement est terminé : ses photos ne sont plus disponibles.
            @else
                Les photos de cet événement ne sont pas encore en ligne. Revenez un peu plus tard !
            @endif
        </p>
    </div>
</x-layouts.guest>
