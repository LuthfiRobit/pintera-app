@props(['variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-brand-500 text-white shadow-sm hover:bg-brand-600',
        'ghost' => 'border border-gray-200 text-gray-700 hover:bg-gray-50',
    ];
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition active:scale-[0.98] ' . ($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</a>
