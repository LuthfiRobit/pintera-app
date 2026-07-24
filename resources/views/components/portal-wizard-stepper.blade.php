{{-- resources/views/components/portal-wizard-stepper.blade.php --}}
@props(['current'])

@php
    $stages = [
        'data-diri' => 'Data Diri',
        'formulir-tambahan' => 'Formulir Tambahan',
        'dokumen' => 'Dokumen',
        'review' => 'Review',
    ];
    $keys = array_keys($stages);
    $currentIndex = array_search($current, $keys, true);
@endphp

<div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-10">
    <div class="flex items-center justify-center gap-3 overflow-x-auto rounded-2xl border border-gray-200 bg-white px-4 py-4 max-[560px]:justify-start sm:px-6">
        @foreach ($stages as $key => $label)
            @php
                $index = array_search($key, $keys, true);
                $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'active' : 'upcoming');
            @endphp
            <div class="flex shrink-0 items-center gap-2.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold {{ $state === 'done' ? 'bg-success-500 text-white' : ($state === 'active' ? 'bg-portal-500 text-white' : 'bg-gray-100 text-gray-400') }}">
                    @if ($state === 'done')
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="whitespace-nowrap text-[12.5px] font-semibold {{ $state === 'active' ? 'text-portal-500' : ($state === 'done' ? 'text-gray-900' : 'text-gray-400') }}">
                    {{ $label }}
                </span>
            </div>
            @if (! $loop->last)
                <span class="h-0.5 w-8 shrink-0 {{ $index < $currentIndex ? 'bg-success-500' : 'bg-gray-200' }}"></span>
            @endif
        @endforeach
    </div>
</div>
