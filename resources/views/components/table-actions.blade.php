{{--
    Tombol pill "Aksi" + dropdown, dipakai di kolom paling kiri setiap tabel data index.

    Dropdown-nya di-teleport ke <body> dan diposisikan lewat plugin resmi Alpine Anchor
    (@alpinejs/anchor, berbasis Floating UI — pendekatan yang sama dipakai Flowbite/Popper)
    supaya tidak ikut terpotong oleh container overflow-x-auto tabel atau kolom sticky.
--}}
<div x-data="{ open: false }" class="inline-block">
    <button
        type="button"
        x-ref="trigger"
        @click="open = !open"
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-brand-500 py-1.5 pl-2.5 pr-2.5 text-xs font-semibold text-white transition hover:bg-brand-600"
    >
        <x-icon name="settings" class="h-3.5 w-3.5" />
        Aksi
        <x-icon name="expand_more" class="h-3 w-3" />
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-anchor.bottom-start.offset.6="$refs.trigger"
            @click.outside="if ($event.target !== $refs.trigger && !$refs.trigger.contains($event.target)) open = false"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="z-50 w-56 rounded-2xl border border-gray-200 bg-white py-2 shadow-elevated"
            style="display: none;"
            @click="open = false"
        >
            {{ $slot }}
        </div>
    </template>
</div>
