@vite('resources/js/uploader.js')

<x-filament::section
    icon="heroicon-o-cloud-arrow-up"
    heading="Envoyer des photos"
    :description="'Les photos sont publiées' . ($event->watermark_enabled ? ', filigranées' : '') . ($event->faceSearchEnabled() ? ' et analysées pour la reconnaissance faciale' : '') . ' automatiquement. Un lot interrompu peut être relancé sans créer de doublons.'"
>
    <x-slot name="afterHeader">
        <x-filament::link :href="$event->publicUrl()" target="_blank" icon="heroicon-o-arrow-top-right-on-square" icon-position="after">
            Page invités
        </x-filament::link>
    </x-slot>

    <div
        x-data="{ uppy: null }"
        x-init="uppy = window.trouvmoiUploader($refs.zone, {
            endpoint: @js($uploadUrl),
            csrf: @js(csrf_token()),
            maxMb: @js($maxMb),
            onComplete: () => $wire.$refresh(),
        })"
        x-on:livewire:navigating.window="uppy?.destroy()"
        wire:ignore
    >
        <div x-ref="zone"></div>
    </div>
</x-filament::section>
