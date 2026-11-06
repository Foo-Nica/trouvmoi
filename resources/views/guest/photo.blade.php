<x-layouts.guest :title="$event->title">
    <div class="space-y-4">
        <a href="{{ route('event.show', $event->slug) }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-brand">
            ← {{ __('Retour à la galerie') }}
        </a>

        @php($canSearch = $faceSearch && $consented && $faces->isNotEmpty())

        @if ($canSearch)
            <p class="rounded-xl bg-orange-brand/10 px-4 py-3 text-sm font-medium text-indigo-brand">
                {{ $faces->count() > 1 ? __('Touchez votre visage pour retrouver toutes vos photos.') : __('Touchez le visage pour retrouver toutes les photos de cette personne.') }}
            </p>
        @endif

        <div class="relative overflow-hidden rounded-2xl bg-slate-900 shadow-sm">
            <img src="{{ $photo->temporaryUrl('preview', 60) }}" alt="" class="block h-auto w-full">

            @if ($canSearch)
                @foreach ($faces as $face)
                    <form method="POST" action="{{ route('event.search.face', $event->slug) }}"
                          class="absolute" style="left: {{ $face['left'] }}%; top: {{ $face['top'] }}%; width: {{ $face['width'] }}%; height: {{ $face['height'] }}%;">
                        @csrf
                        <input type="hidden" name="face_id" value="{{ $face['id'] }}">
                        <button class="h-full w-full rounded-lg border-2 border-white/90 bg-white/5 shadow-[0_0_0_2px_rgba(254,119,55,.9)] transition hover:bg-orange-brand/25 focus:bg-orange-brand/30 focus:outline-none"
                                aria-label="{{ __('C\'est moi') }}"></button>
                    </form>
                @endforeach
            @endif
        </div>

        @if ($faceSearch && ! $consented)
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 @if (session('needs_consent')) ring-2 ring-orange-brand @endif">
                <h2 class="mb-3 text-lg font-semibold text-indigo-brand">{{ __('Vous êtes sur cette photo ?') }}</h2>
                @include('guest.partials.consent', ['next' => route('event.photo', [$event->slug, $photo->id], false)])
            </section>
        @elseif ($faceSearch && $faces->isEmpty())
            <p class="text-sm text-slate-500">{{ __('Aucun visage n\'a été repéré sur cette photo. Choisissez-en une autre où votre visage est bien visible.') }}</p>
        @endif

        @if ($photo->download_path)
            <a href="{{ route('event.download', [$event->slug, $photo->id]) }}"
               class="flex w-full items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-indigo-brand shadow-sm ring-1 ring-slate-300">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                {{ __('Télécharger cette photo') }}
            </a>
        @endif

        <details class="rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
            <summary class="cursor-pointer font-medium text-slate-600">{{ __('Demander le retrait de cette photo') }}</summary>
            <form method="POST" action="{{ route('event.removal', [$event->slug, $photo->id]) }}" class="mt-3 space-y-3">
                @csrf
                <textarea name="reason" rows="2" maxlength="500" placeholder="{{ __('Motif (facultatif)') }}"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                <input name="contact" maxlength="120" placeholder="{{ __('Téléphone ou e-mail (facultatif)') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-lg bg-slate-800 px-4 py-2 font-medium text-white">{{ __('Envoyer la demande') }}</button>
            </form>
        </details>
    </div>
</x-layouts.guest>
