@props(['isYayasan' => false, 'activeLembaga' => null])

@if ($isYayasan)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ' . ($activeLembaga ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700')]) }}>
        <x-icon name="apartment" class="h-3.5 w-3.5" />
        {{ $activeLembaga ? $activeLembaga->nama : 'Semua Lembaga' }}
    </span>
@endif
