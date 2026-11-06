<x-layouts.guest :title="__('Vos photos')" wide>
    <form method="POST" action="{{ route('folder.store', $event->slug) }}"
          x-data="results({{ $strong->count() }})" x-on:change="update" class="pb-28">
        @csrf
        <input type="hidden" name="search" value="{{ $search }}">

        <div class="mb-4">
            <a href="{{ route('event.show', $event->slug) }}" class="text-sm font-medium text-indigo-brand">← {{ $event->title }}</a>
            <h1 class="mt-2 text-2xl font-bold text-indigo-brand">
                @if ($strong->isEmpty() && $maybe->isEmpty())
                    {{ __('Aucune photo trouvée pour l\'instant') }}
                @else
                    {{ trans_choice('Nous avons trouvé :count photo de vous|Nous avons trouvé :count photos de vous', $strong->count()) }}
                @endif
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                @if ($strong->isEmpty() && $maybe->isEmpty())
                    {{ __('Essayez avec un autre visage ou un selfie bien éclairé. De nouvelles photos peuvent aussi arriver pendant l\'événement.') }}
                @else
                    {{ __('Décochez les photos où ce n\'est pas vous, puis créez votre dossier.') }}
                @endif
            </p>
        </div>

        @foreach ([[__('Vos photos'), $strong, true], [__('Peut-être vous'), $maybe, false]] as [$heading, $items, $checked])
            @if ($items->isNotEmpty())
                <section class="mb-6">
                    <h2 class="mb-2 flex items-baseline gap-2 font-semibold text-indigo-brand">
                        {{ $heading }} <span class="text-sm font-normal text-slate-500">({{ $items->count() }})</span>
                    </h2>
                    @unless ($checked)
                        <p class="mb-2 text-sm text-slate-500">{{ __('Moins sûres : cochez celles où vous apparaissez.') }}</p>
                    @endunless
                    <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4 xl:grid-cols-6">
                        @foreach ($items as $item)
                            <label class="group relative block aspect-square cursor-pointer overflow-hidden rounded-lg bg-slate-200">
                                <input type="checkbox" name="photos[]" value="{{ $item['photo_id'] }}" @checked($checked) class="peer sr-only">
                                <img src="{{ $item['thumb'] }}" alt="" loading="lazy" class="h-full w-full object-cover transition peer-[:not(:checked)]:opacity-40 peer-[:not(:checked)]:grayscale">
                                <span class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-black/30 text-white peer-checked:border-orange-brand peer-checked:bg-orange-brand">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if ($strong->isNotEmpty() || $maybe->isNotEmpty())
            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur" style="padding-bottom: max(.75rem, env(safe-area-inset-bottom))">
                <div class="mx-auto flex max-w-5xl items-center gap-3">
                    <input name="name" maxlength="60" placeholder="{{ __('Nom du dossier (facultatif)') }}"
                           class="hidden min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-3 text-sm sm:block">
                    <button :disabled="count === 0"
                            class="flex-1 rounded-xl bg-orange-brand px-5 py-3.5 font-semibold text-white shadow-sm disabled:opacity-40 sm:flex-none">
                        {{ __('Créer mon dossier') }} (<span x-text="count">{{ $strong->count() }}</span>)
                    </button>
                </div>
            </div>
        @endif
    </form>

    @if ($strong->isEmpty() && $maybe->isEmpty())
        <div class="mt-2">@include('guest.partials.selfie', ['label' => __('Réessayer avec un selfie')])</div>
    @endif
</x-layouts.guest>
