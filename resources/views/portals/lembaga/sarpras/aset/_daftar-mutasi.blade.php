<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Mutasi Lokasi</p>
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
                    <th class="px-5 py-3">Tanggal</th>
                    <th class="px-5 py-3">Barang & Kode</th>
                    <th class="px-5 py-3">Dari Ruangan</th>
                    <th class="px-5 py-3">Ke Ruangan</th>
                    <th class="px-5 py-3 text-center">Jumlah</th>
                    <th class="px-5 py-3">Alasan Pemindahan</th>
                    <th class="px-5 py-3">Petugas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($mutasiList as $m)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.sarpras.aset.show', $m->aset_barang_id)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="visibility" class="h-4 w-4 text-gray-500" />
                                        Lihat Detail Aset
                                    </span>
                                </x-dropdown-link>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 text-gray-900 font-medium whitespace-nowrap">{{ $m->tanggal_mutasi->translatedFormat('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.sarpras.aset.show', $m->aset_barang_id) }}" class="font-semibold text-brand-600 hover:underline">
                                {{ $m->asetBarang->nama_barang ?? '-' }}
                            </a>
                            <div class="font-mono text-xs text-gray-400 mt-0.5">{{ $m->asetBarang->kode_inventaris ?? '' }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $m->ruanganAsal->nama_ruangan ?? '-' }}</td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $m->ruanganTujuan->nama_ruangan ?? '-' }}</td>
                        <td class="px-5 py-3.5 text-center font-semibold text-gray-900">{{ $m->qty_pindah }} unit</td>
                        <td class="px-5 py-3.5 text-gray-600 max-w-xs truncate">{{ $m->alasan_mutasi }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $m->dilakukanOleh->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->filled('search'))
                                Tidak ada riwayat mutasi yang cocok dengan kata kunci pencarian.
                            @else
                                Belum ada riwayat mutasi aset yang tercatat.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-5 py-4">
        {{ $mutasiList->links('pagination.tailadmin') }}
    </div>
</div>
