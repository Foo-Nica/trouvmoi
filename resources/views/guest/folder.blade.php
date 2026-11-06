<x-layouts.guest :title="$folder->name ?? 'Mon dossier'">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-semibold uppercase tracking-wide text-orange-brand">{{ $folder->event->title }}</p>
        <h1 class="mt-1 text-xl font-semibold text-indigo-brand">{{ $folder->name ?? 'Mes photos' }}</h1>
        <p class="mt-2 text-slate-600">{{ $folder->keptPhotos()->count() }} photo(s) dans ce dossier.</p>
    </div>
</x-layouts.guest>
