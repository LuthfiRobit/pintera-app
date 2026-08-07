@props(['disabled' => false, 'error' => false])

@php
    $baseClasses = 'w-full rounded-lg text-sm px-3.5 py-2.5 transition-all focus:outline-none focus:ring-4 disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed';
    $stateClasses = $error 
        ? 'border-error-300 text-error-900 focus:border-error-500 focus:ring-error-500/20 bg-error-50/30' 
        : 'border-gray-200 text-gray-900 focus:border-brand-500 focus:ring-brand-500/20 bg-white hover:border-gray-300';
@endphp

<input @disabled($disabled) {{ $attributes->merge(['class' => $baseClasses . ' ' . $stateClasses]) }}>
