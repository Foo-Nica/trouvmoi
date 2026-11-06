<x-layouts.guest :title="$event->title" wide>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,22rem)_1fr] lg:items-start">
        <div class="space-y-5 lg:sticky lg:top-20">
            @include('guest.partials.event-header', ['showDescription' => true])

            @if ($faceSearch)
                <section id="rechercher" class="scroll-mt-20 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-lg font-semibold text-indigo-brand">{{ __('Retrouver mes photos') }}</h2>

                    @if (! $consented)
                        <p class="mb-4 mt-1 text-sm text-slate-600">
                            {{ __('Montrez votre visage une fois : nous rassemblons toutes les photos où vous apparaissez.') }}
                        </p>
                        @include('guest.partials.consent', ['next' => route('event.show', $event->slug, false).'#rechercher'])
                    @elseif ($galleryVisible)
                        <ol class="mb-4 mt-2 space-y-1.5 text-sm text-slate-600">
                            <li><span class="font-semibold text-orange-brand">1.</span> {{ __('Touchez une photo de la galerie où vous apparaissez.') }}</li>
                            <li><span class="font-semibold text-orange-brand">2.</span> {{ __('Touchez votre visage sur cette photo.') }}</li>
                        </ol>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('ou bien') }}</p>
                        @include('guest.partials.selfie', ['primary' => false, 'label' => __('Chercher avec un selfie')])
                    @else
                        <p class="mb-4 mt-1 text-sm text-slate-600">
                            {{ __('Prenez un selfie de face, bien éclairé : vous verrez uniquement vos photos.') }}
                        </p>
                        @include('guest.partials.selfie')
                    @endif

                    @if ($folders->isNotEmpty())
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <h3 class="text-sm font-semibold text-slate-700">{{ __('Mes dossiers') }}</h3>
                            <ul class="mt-2 space-y-2">
                                @foreach ($folders as $folder)
                                    <li>
                                        <a href="{{ $folder->privateUrl() }}" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm hover:bg-slate-100">
                                            <span class="font-medium text-indigo-brand">{{ $folder->name ?: __('Mes photos') }}</span>
                                            <span class="text-slate-500">{{ trans_choice(':count photo|:count photos', $folder->keptPhotos()->count()) }} →</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif
        </div>

        @if ($galleryVisible)
            <section x-data="gallery(@js($firstPage), @js(route('event.photos', $event->slug)))">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold text-indigo-brand">{{ __('Toutes les photos') }}</h2>
                    <span class="text-sm text-slate-500">{{ trans_choice(':count photo|:count photos', $firstPage['total']) }}</span>
                </div>

                @if ($firstPage['total'] === 0)
                    <div class="rounded-2xl bg-white p-8 text-center text-slate-500 ring-1 ring-slate-200">
                        {{ __('Les photos arrivent bientôt. Revenez un peu plus tard !') }}
                    </div>
                @endif

                <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 xl:grid-cols-5">
                    <template x-for="photo in photos" :key="photo.id">
                        <a :href="photo.url" class="group relative block aspect-square overflow-hidden rounded-md bg-slate-200">
                            <img :src="photo.thumb" alt="" loading="lazy" decoding="async"
                                 class="h-full w-full object-cover transition group-hover:scale-105">
                        </a>
                    </template>
                </div>
                <div x-ref="sentinel" class="h-10"></div>
                <p x-show="loading" x-cloak class="py-3 text-center text-sm text-slate-500">{{ __('Chargement…') }}</p>
            </section>
        @endif
    </div>
</x-layouts.guest>
