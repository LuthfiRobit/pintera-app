@props(['tone' => 'slate'])

@php
    $tones = [
        'brass' => 'bg-brass/10 text-brass',
        'green' => 'bg-signal-green/10 text-signal-green',
        'red' => 'bg-signal-red/10 text-signal-red',
        'amber' => 'bg-signal-amber/10 text-signal-amber',
        'blue' => 'bg-spmb-accent/10 text-spmb-primary',
        'slate' => 'bg-slate/10 text-slate',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
