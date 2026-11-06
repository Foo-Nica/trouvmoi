<article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    @if ($event->cover_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk(\App\Models\Photo::disk())->temporaryUrl($event->cover_path, now()->addHour()) }}"
             alt="" class="h-40 w-full object-cover sm:h-56">
    @endif
    <div class="p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-orange-brand">{{ $event->owner->displayName() }}</p>
        <h1 class="mt-1 text-2xl font-bold leading-tight text-indigo-brand">{{ $event->title }}</h1>
        @php($meta = collect([$event->event_date?->translatedFormat('j F Y'), $event->location])->filter()->join(' · '))
        @if ($meta)
            <p class="mt-1 text-sm text-slate-500">{{ $meta }}</p>
        @endif
        @if (! empty($showDescription) && $event->description)
            <p class="mt-3 text-slate-700">{{ $event->description }}</p>
        @endif
    </div>
</article>
