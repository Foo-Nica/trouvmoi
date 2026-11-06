{{-- Notice de consentement : obligatoire avant toute recherche faciale. --}}
<form method="POST" action="{{ route('event.consent', $event->slug) }}" class="space-y-4">
    @csrf
    <input type="hidden" name="next" value="{{ $next ?? '' }}">
    <div class="rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700 ring-1 ring-slate-200">
        <p class="font-semibold text-indigo-brand">{{ __('Avant de commencer') }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>{{ __('Pour retrouver vos photos, nous comparons votre visage à ceux des photos de cet événement.') }}</li>
            <li>{{ __('Le selfie éventuel sert uniquement à la recherche : il est effacé aussitôt, jamais enregistré.') }}</li>
            <li>{{ __('Les données faciales de l\'événement sont supprimées automatiquement le :date.', ['date' => $event->expires_at?->translatedFormat('j F Y')]) }}</li>
            <li>{{ __('Vous pouvez demander le retrait d\'une photo à tout moment.') }}</li>
        </ul>
        <a href="{{ route('legal.privacy') }}" target="_blank" class="mt-2 inline-block font-medium text-orange-brand underline">{{ __('En savoir plus sur vos données') }}</a>
    </div>
    <label class="flex items-start gap-3 text-sm text-slate-700">
        <input type="checkbox" name="accept" value="1" required class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 accent-orange-brand">
        <span>{{ __('J\'accepte que mon visage soit analysé pour retrouver mes photos de cet événement.') }}</span>
    </label>
    <button class="w-full rounded-xl bg-orange-brand px-5 py-3.5 font-semibold text-white shadow-sm active:scale-[.99]">
        {{ __('Continuer') }}
    </button>
</form>
