<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Kategori</p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                <option value="10">10 / hal</option>
                <option value="20">20 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
        </div>
    </div>

    <div class="relative overflow-x-auto">
        <!-- Loading overlay -->
        <div x-show="false" class="absolute inset-0 z-20 flex items-center justify-center bg-white/50 backdrop-blur-sm"
             x-transition.opacity
             @ajax-start.window="$el.style.display = 'flex'"
             @ajax-end.window="$el.style.display = 'none'">
            <x-icon name="sync" class="h-8 w-8 animate-spin text-brand-500" />
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                    <th class="px-5 py-3">Kode</th>
                    <th class="px-5 py-3">Nama Kategori</th>
                    <th class="px-5 py-3 text-center">Total Aset</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($kategoriList as $k)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <form
                                    action="{{ route('admin.sarpras.kategori.destroy', $k) }}"
                                    method="POST"
                                    x-data
                                    @submit.prevent="confirmDialog('Hapus Kategori Aset?', @js('Apakah Anda yakin ingin menghapus kategori ' . $k->nama_kategori . '?'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition">
                                        <x-icon name="delete" class="h-4 w-4 text-rose-500" />
                                        Hapus Kategori
                                    </button>
                                </form>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-mono font-bold text-brand-600">
                            {{ $k->kode_kategori }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-semibold text-gray-900">{{ $k->nama_kategori }}</span>
                            @if ($k->deskripsi)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $k->deskripsi }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <x-badge tone="blue">{{ $k->aset_count }} Item</x-badge>
                        </td>
                    </tr>
                @endforeach

                @if ($kategoriList->isEmpty())
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->filled('search'))
                                Tidak ada kategori yang cocok dengan pencarian.
                            @else
                                Belum ada kategori aset yang didaftarkan.
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-5 py-4">
        {{ $kategoriList->links('pagination.tailadmin') }}
    </div>
</div>
