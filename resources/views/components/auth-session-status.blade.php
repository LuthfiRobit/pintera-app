@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-signal-green']) }}>
        {{ $status }}
    </div>
@endif
