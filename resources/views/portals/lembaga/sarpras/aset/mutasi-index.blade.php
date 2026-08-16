<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold text-gray-900">Riwayat Mutasi & Perpindahan Aset</h1>
                <p class="text-xs text-gray-500 mt-1">Audit log perpindahan seluruh barang inventaris antar-ruangan di lingkungan lembaga.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sarpras.aset.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition">
                    <x-icon name="arrow_back" class="h-4 w-4" />
                    Kembali ke Daftar Aset
                </a>
            </div>
        </div>

        {{-- Table Container --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3.5">Tanggal</th>
                            <th class="px-6 py-3.5">Barang & Kode</th>
                            <th class="px-6 py-3.5">Dari Ruangan</th>
                            <th class="px-6 py-3.5">Ke Ruangan</th>
                            <th class="px-6 py-3.5 text-center">Jumlah</th>
                            <th class="px-6 py-3.5">Alasan Pemindahan</th>
                            <th class="px-6 py-3.5">Petugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($mutasiList as $m)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4">{{ $m->tanggal_mutasi->translatedFormat('d M Y') }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900">{{ $m->asetBarang->nama_barang ?? '-' }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $m->asetBarang->kode_inventaris ?? '' }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $m->ruanganAsal->nama_ruangan ?? '-' }}</td>
                                <td class="px-6 py-4 font-semibold text-gray-900">{{ $m->ruanganTujuan->nama_ruangan ?? '-' }}</td>
                                <td class="px-6 py-4 text-center">{{ $m->qty_pindah }} unit</td>
                                <td class="px-6 py-4 text-gray-700 max-w-xs truncate">{{ $m->alasan_mutasi }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $m->dilakukanOleh->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">Belum ada riwayat mutasi aset.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($mutasiList->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $mutasiList->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
