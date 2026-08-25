@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'default', 'compact' => false])

@php
    $tones = [
        'default' => ['icon' => 'bg-gray-100 text-gray-600', 'value' => 'text-gray-900'],
        'green' => ['icon' => 'bg-emerald-50 text-emerald-600', 'value' => 'text-emerald-700'],
        'red' => ['icon' => 'bg-rose-50 text-rose-600', 'value' => 'text-rose-700'],
        'amber' => ['icon' => 'bg-amber-50 text-amber-600', 'value' => 'text-amber-700'],
        'blue' => ['icon' => 'bg-blue-50 text-blue-600', 'value' => 'text-blue-700'],
        'indigo' => ['icon' => 'bg-indigo-50 text-indigo-600', 'value' => 'text-indigo-700'],
    ];
    $toneStyle = $tones[$tone] ?? $tones['default'];
@endphp

@if ($compact)
    {{-- Varian padat: ikon bulat di kiri, label+angka bertumpuk di kanan - untuk grid rapat
         (mis. status Hadir/Izin/Sakit/Alpa/Cuti berdampingan di satu baris). --}}
    <div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-card transition hover:shadow-elevated']) }}>
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $toneStyle['icon'] }}">
                <x-icon :name="$icon" class="h-[16px] w-[16px]" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="truncate font-display text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <p class="font-display text-lg font-bold leading-tight {{ $toneStyle['value'] }} sm:text-xl">{{ $value }}</p>
        </div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-elevated sm:p-5']) }}>
        <div class="flex items-center justify-between gap-2">
            <p class="min-w-0 truncate font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $label }}</p>
            @if ($icon)
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $toneStyle['icon'] }}">
                    <x-icon :name="$icon" class="h-[18px] w-[18px]" />
                </span>
            @endif
        </div>
        <p class="mt-2 font-display text-2xl font-bold leading-tight {{ $toneStyle['value'] }} sm:text-3xl">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 truncate text-xs text-gray-500">{{ $hint }}</p>
        @endif
    </div>
@endif
