<x-layouts.guest :title="$folder->name ?: __('Mes photos')" wide>
    <div class="mb-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-semibold uppercase tracking-wide text-orange-brand">{{ $event->title }}</p>
        <h1 class="mt-1 text-2xl font-bold text-indigo-brand">{{ $folder->name ?: __('Mes photos') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ trans_choice(':count photo|:count photos', $photos->count()) }} · {{ __('Photos de :name', ['name' => $event->owner->displayName()]) }}
        </p>

        @if (session('created'))
            <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ __('Dossier créé ! Gardez ce lien : vous y retrouverez vos photos, même celles envoyées plus tard.') }}
            </p>
        @elseif ($added > 0)
            <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ trans_choice(':count nouvelle photo ajoutée depuis votre dernière visite.|:count nouvelles photos ajoutées depuis votre dernière visite.', $added) }}
            </p>
        @endif

        <div class="mt-4 grid gap-2 sm:grid-cols-3" x-data="share(@js($folder->privateUrl()), @js(__('Mes photos de :event', ['event' => $event->title])))">
            @if ($photos->isNotEmpty())
                <a href="{{ route('folder.zip', $folder->access_token) }}"
                   class="flex items-center justify-center gap-2 rounded-xl bg-orange-brand px-4 py-3 font-semibold text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                    {{ __('Tout télécharger') }}
                </a>
            @endif
            <a href="https://wa.me/?text={{ rawurlencode(__('Mes photos de :event', ['event' => $event->title]).' : '.$folder->privateUrl()) }}"
               target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 font-semibold text-white shadow-sm">
                WhatsApp
            </a>
            <button type="button" x-on:click="native"
                    class="flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 font-semibold text-indigo-brand ring-1 ring-slate-300">
                <span x-show="!copied">{{ __('Copier le lien') }}</span>
                <span x-show="copied" x-cloak>{{ __('Lien copié !') }}</span>
            </button>
        </div>
    </div>

    @if ($photos->isEmpty())
        <div class="rounded-2xl bg-white p-8 text-center text-slate-500 ring-1 ring-slate-200">{{ __('Ce dossier est vide.') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($photos as $photo)
            <figure class="overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                <a href="{{ $photo->temporaryUrl('preview', 120) }}" target="_blank" class="block aspect-square bg-slate-200">
                    <img src="{{ $photo->temporaryUrl('thumb', 120) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                </a>
                <figcaption class="flex items-center justify-between gap-1 px-2 py-1.5">
                    <a href="{{ route('folder.download', [$folder->access_token, $photo->id]) }}"
                       class="rounded-md px-2 py-1 text-sm font-medium text-indigo-brand hover:bg-slate-100">{{ __('Télécharger') }}</a>
                    @if ($isOwner)
                        <form method="POST" action="{{ route('folder.exclude', [$folder->access_token, $photo->id]) }}">
                            @csrf
                            <button class="rounded-md px-2 py-1 text-xs text-slate-500 hover:bg-slate-100">{{ __('Ce n\'est pas moi') }}</button>
                        </form>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-3 text-sm">
        <a href="{{ route('event.show', $event->slug) }}" class="font-medium text-indigo-brand">← {{ __('Toutes les photos de l\'événement') }}</a>
        @if ($isOwner)
            <form method="POST" action="{{ route('folder.destroy', $folder->access_token) }}"
                  onsubmit="return confirm(@js(__('Supprimer ce dossier ? Les photos restent sur la page de l\'événement.')))">
                @csrf @method('DELETE')
                <button class="text-slate-500 underline">{{ __('Supprimer ce dossier') }}</button>
            </form>
        @endif
    </div>
</x-layouts.guest>
