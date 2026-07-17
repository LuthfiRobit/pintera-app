@props(['tone' => 'slate'])

@php
    $tones = [
        'brass' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-success-50 text-success-700',
        'red' => 'bg-error-50 text-error-700',
        'amber' => 'bg-warning-50 text-warning-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'slate' => 'bg-gray-100 text-gray-600',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
