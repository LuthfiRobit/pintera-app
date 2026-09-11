@props(['text' => '', 'position' => 'top', 'align' => 'center', 'asButton' => false])

@php
    $placement = match($position) {
        'bottom' => ($align === 'right' ? 'bottom-end' : ($align === 'left' ? 'bottom-start' : 'bottom')),
        'left' => 'left',
        'right' => 'right',
        default => ($align === 'right' ? 'top-end' : ($align === 'left' ? 'top-start' : 'top')),
    };
@endphp

<div x-data="{ showTooltip: false }" @click.outside="showTooltip = false" class="relative inline-flex items-center">
    @if ($asButton)
        {{-- Trigger interaktif (klik untuk toggle) -- HANYA aman dipakai kalau slot BUKAN elemen interaktif lain
             (link/button/form), karena <button> tidak boleh berisi elemen interaktif lain. --}}
        <button
            type="button"
            x-ref="trigger"
            @mouseenter="showTooltip = true"
            @mouseleave="showTooltip = false"
            @click.stop="showTooltip = !showTooltip"
            @focusin="showTooltip = true"
            @focusout="showTooltip = false"
            class="inline-flex items-center cursor-help text-gray-400 hover:text-gray-600 focus:outline-none transition p-0.5 rounded"
        >
            {{ $slot }}
        </button>
    @else
        {{-- Default: trigger non-interaktif (hover/focus saja), aman membungkus elemen apa pun di slot
             (link, button, form) tanpa melanggar aturan nesting HTML. --}}
        <span
            x-ref="trigger"
            tabindex="0"
            @mouseenter="showTooltip = true"
            @mouseleave="showTooltip = false"
            @focusin="showTooltip = true"
            @focusout="showTooltip = false"
            class="inline-flex items-center focus:outline-none"
        >
            {{ $slot }}
        </span>
    @endif

    <template x-teleport="body">
        <div
            x-show="showTooltip"
            x-anchor.{{ $placement }}.offset.8="$refs.trigger"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="pointer-events-none z-[99999] w-max max-w-[280px] rounded-xl bg-[#1E293B] px-3.5 py-2 text-center text-xs font-medium leading-relaxed text-white shadow-xl shadow-slate-900/30"
            style="display: none;"
        >
            {{ $text }}
        </div>
    </template>
</div>
