@props(['label', 'value', 'hint' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:shadow-elevated']) }}>
    <div class="flex items-center justify-between">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                <x-icon :name="$icon" class="h-[18px] w-[18px]" />
            </span>
        @endif
    </div>
    <p class="mt-2 font-display text-3xl font-bold text-gray-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
