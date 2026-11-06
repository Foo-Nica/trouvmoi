<x-layouts.guest :title="$event->title">
    <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($event->cover_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk(\App\Models\Photo::disk())->temporaryUrl($event->cover_path, now()->addHour()) }}"
                 alt="" class="h-48 w-full object-cover sm:h-64">
        @endif
        <div class="p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-orange-brand">
                {{ $event->owner->displayName() }}
            </p>
            <h1 class="mt-1 text-2xl font-bold text-indigo-brand">{{ $event->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ collect([$event->event_date?->translatedFormat('j F Y'), $event->location])->filter()->join(' · ') }}
            </p>
            @if ($event->description)
                <p class="mt-4 text-slate-700">{{ $event->description }}</p>
            @endif
            <p class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                {{ number_format($event->photos_count, 0, ',', ' ') }} photos publiées.
                La recherche de vos photos par reconnaissance faciale arrive très bientôt sur cette page.
            </p>
        </div>
    </article>
</x-layouts.guest>
