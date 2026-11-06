@props(['title'])
<section class="mt-8">
    <h2 class="text-lg font-semibold text-indigo-brand">{{ $title }}</h2>
    <div class="mt-2 space-y-3 text-sm leading-relaxed text-slate-700 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5">
        {{ $slot }}
    </div>
</section>
