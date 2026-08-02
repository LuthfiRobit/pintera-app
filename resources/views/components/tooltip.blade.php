@props(['text' => ''])

<div x-data="{ showTooltip: false }" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false" @focusin="showTooltip = true" @focusout="showTooltip = false" class="relative inline-flex items-center">
    {{ $slot }}
    <div
        x-show="showTooltip"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2.5 w-max max-w-[260px] -translate-x-1/2 rounded-xl bg-[#1E293B] px-3.5 py-2 text-center text-xs font-medium leading-relaxed text-white shadow-xl shadow-slate-900/20"
        style="display: none;"
    >
        {{ $text }}
        {{-- Tooltip Arrow --}}
        <div class="absolute -bottom-1 left-1/2 h-2.5 w-2.5 -translate-x-1/2 rotate-45 bg-[#1E293B]"></div>
    </div>
</div>
