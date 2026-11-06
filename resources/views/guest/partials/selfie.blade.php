<form x-data="selfie" x-ref="form" method="POST" enctype="multipart/form-data"
      action="{{ route('event.search.selfie', $event->slug) }}">
    @csrf
    <input x-ref="upload" type="file" name="selfie" class="hidden">
    <label @class([
        'flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3.5 font-semibold shadow-sm active:scale-[.99]',
        'bg-orange-brand text-white' => $primary ?? true,
        'bg-white text-indigo-brand ring-1 ring-slate-300' => ! ($primary ?? true),
    ])>
        <input type="file" accept="image/*" capture="user" class="sr-only" x-on:change="pick" :disabled="busy">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 0 1 2-2h1.2a2 2 0 0 0 1.7-.9l.8-1.2A2 2 0 0 1 10.4 4h3.2a2 2 0 0 1 1.7.9l.8 1.2a2 2 0 0 0 1.7.9H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/><circle cx="12" cy="13" r="3.5"/></svg>
        <span x-show="!busy">{{ $label ?? __('Prendre un selfie') }}</span>
        <span x-show="busy" x-cloak>{{ __('Recherche en cours…') }}</span>
    </label>
</form>
