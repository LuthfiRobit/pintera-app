<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Riwayat Mutasi Aset</h1>
                <p class="text-xs text-gray-500 mt-0.5">Audit log perpindahan seluruh barang inventaris antar-ruangan.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-link-button variant="secondary" href="{{ route('admin.sarpras.aset.index') }}">
                    <x-icon name="arrow_back" class="h-4 w-4" /> Kembali ke Daftar Aset
                </x-link-button>
            </div>
        </div>

        {{-- Table Container --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Barang & Kode</th>
                            <th class="px-5 py-3">Dari Ruangan</th>
                            <th class="px-5 py-3">Ke Ruangan</th>
                            <th class="px-5 py-3 text-center">Jumlah</th>
                            <th class="px-5 py-3">Alasan Pemindahan</th>
                            <th class="px-5 py-3">Petugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($mutasiList as $m)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-5 py-3.5 text-gray-900">{{ $m->tanggal_mutasi->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="font-semibold text-gray-900">{{ $m->asetBarang->nama_barang ?? '-' }}</div>
                                    <div class="font-mono text-xs text-gray-400">{{ $m->asetBarang->kode_inventaris ?? '' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $m->ruanganAsal->nama_ruangan ?? '-' }}</td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $m->ruanganTujuan->nama_ruangan ?? '-' }}</td>
                                <td class="px-5 py-3.5 text-center font-semibold text-gray-900">{{ $m->qty_pindah }} unit</td>
                                <td class="px-5 py-3.5 text-gray-600 max-w-xs truncate">{{ $m->alasan_mutasi }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $m->dilakukanOleh->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-gray-400">Belum ada riwayat mutasi aset.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($mutasiList->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">
                    {{ $mutasiList->links('pagination.tailadmin') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
