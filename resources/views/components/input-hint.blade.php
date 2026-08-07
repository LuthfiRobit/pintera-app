@props(['value'])

<p {{ $attributes->merge(['class' => 'mt-1 text-xs text-gray-500']) }}>
    {{ $value ?? $slot }}
</p>
