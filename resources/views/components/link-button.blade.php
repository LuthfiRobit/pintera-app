@props(['variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-ink text-paper shadow-sm hover:bg-ink/90',
        'ghost' => 'border border-ink/15 text-ink hover:bg-paper',
    ];
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition active:scale-[0.98] ' . ($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</a>
