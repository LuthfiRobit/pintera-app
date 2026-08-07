@props(['disabled' => false, 'error' => false])

@php
    $baseClasses = 'block w-full rounded-lg text-sm shadow-sm transition-colors focus:ring-2 disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed';
    $stateClasses = $error 
        ? 'border-error-300 text-error-900 focus:border-error-500 focus:ring-error-200 bg-error-50/30' 
        : 'border-gray-200 text-gray-900 focus:border-brand-500 focus:ring-brand-200 bg-white';
@endphp

<select @disabled($disabled) {!! $attributes->merge(['class' => $baseClasses . ' ' . $stateClasses]) !!}>
    {{ $slot }}
</select>
