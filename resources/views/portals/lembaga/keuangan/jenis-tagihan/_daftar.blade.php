<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3.5">Aksi</th>
                <th class="px-5 py-3.5">Nama</th>
                <th class="px-5 py-3.5">Kategori</th>
                <th class="px-5 py-3.5">Cicilan</th>
                <th class="px-5 py-3.5">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($jenisTagihanList as $item)
                <tr class="transition hover:bg-gray-50/80">
                    <td class="sticky left-0 z-10 bg-white px-5 py-4 shadow-[1px_0_0_0_#f3f4f6]">
                        <x-table-actions>
                            @can('jenis-tagihan.edit')
                                <x-dropdown-link href="{{ route('admin.jenis-tagihan.edit', $item->id) }}">Edit</x-dropdown-link>
                                @if ($item->kategori->isPpdb())
                                    <x-dropdown-link href="{{ route('admin.jenis-tagihan.nominal', $item->id) }}">Kelola Nominal</x-dropdown-link>
                                @else
                                    <x-dropdown-link href="#" @click.prevent="prosesTagihan({{ $item->id }}, '{{ addslashes($item->nama) }}')">Proses Tagihan</x-dropdown-link>
                                    <x-dropdown-link href="{{ route('admin.jenis-tagihan.monitoring.index', $item->id) }}">Monitoring</x-dropdown-link>
                                @endif
                            @endcan
                            @can('jenis-tagihan.delete')
                                <x-dropdown-link href="#" @click.prevent="deleteItem({{ $item->id }}, '{{ addslashes($item->nama) }}')" class="text-error-600 font-medium">Hapus</x-dropdown-link>
                            @endcan
                        </x-table-actions>
                    </td>
                    <td class="px-5 py-4 font-bold text-gray-900">{{ $item->nama }}</td>
                    <td class="px-5 py-4 font-medium text-gray-600">
                        {{ $item->kategori->label() }}
                    </td>
                    <td class="px-5 py-4 text-gray-600">{{ $item->bisa_dicicil ? 'Maks ' . $item->maks_cicilan . 'x' : 'Tidak dicicil' }}</td>
                    <td class="px-5 py-4">
                        <span
                            class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide shadow-sm
                            {{ $item->tagihan_item_count > 0 ? 'bg-brand-50 border border-brand-200 text-brand-600' : ($item->nominal_jalur_count > 0 ? 'bg-blue-50 border border-blue-200 text-blue-700' : 'bg-gray-50 border border-gray-200 text-gray-600') }}"
                        >
                            {{ $item->tagihan_item_count > 0 ? 'Dipakai di ' . $item->tagihan_item_count . ' Tagihan' : ($item->nominal_jalur_count > 0 ? $item->nominal_jalur_count . ' Dikonfigurasi' : 'Belum Dipakai') }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-sm font-medium text-gray-500">Belum ada jenis tagihan yang ditambahkan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($jenisTagihanList->hasPages())
    <div class="border-t border-gray-200 bg-gray-50/50 px-5 py-3">
        {{ $jenisTagihanList->links() }}
    </div>
@endif
