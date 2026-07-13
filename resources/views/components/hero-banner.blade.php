@props(['eyebrow' => null, 'title', 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-[#123363] to-ink p-8 text-paper shadow-elevated md:p-10']) }}>
    <div class="pointer-events-none absolute -right-10 -top-16 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-10 -left-6 h-48 w-48 rounded-full bg-brass/20 blur-2xl"></div>

    <div class="relative z-10 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
        <div class="max-w-xl">
            @if ($eyebrow)
                <span class="inline-block rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-wider">{{ $eyebrow }}</span>
            @endif
            <h1 class="mt-3 font-display text-2xl font-bold md:text-3xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-2 text-sm text-paper/80 md:text-base">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap gap-3">
                {{ $actions }}
            </div>
        @endisset
    </div>

    @if ($slot->isNotEmpty())
        <div class="relative z-10 mt-6">
            {{ $slot }}
        </div>
    @endif
</section>
