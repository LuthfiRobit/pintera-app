{{-- Tombol pill "Aksi" + dropdown, dipakai di kolom paling kiri setiap tabel data index. --}}
<x-dropdown align="left" width="w-56">
    <x-slot name="trigger">
        <button type="button" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-brand-500 py-1.5 pl-2.5 pr-2.5 text-xs font-semibold text-white transition hover:bg-brand-600">
            <x-icon name="settings" class="h-3.5 w-3.5" />
            Aksi
            <x-icon name="expand_more" class="h-3 w-3" />
        </button>
    </x-slot>

    <x-slot name="content">
        {{ $slot }}
    </x-slot>
</x-dropdown>
