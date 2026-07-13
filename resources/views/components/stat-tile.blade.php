@props(['label', 'value', 'hint' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-ink/10 bg-white p-5 shadow-card transition hover:shadow-elevated']) }}>
    <div class="flex items-center justify-between">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-slate">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brass/10 text-brass">
                <span class="material-symbols-outlined" style="font-size: 18px;">{{ $icon }}</span>
            </span>
        @endif
    </div>
    <p class="mt-2 font-display text-3xl font-bold text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate">{{ $hint }}</p>
    @endif
</div>
