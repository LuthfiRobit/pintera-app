<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold text-gray-900">Rekapitulasi Sarpras & Aset Yayasan</h1>
                <p class="text-xs text-gray-500 mt-1">Konsolidasi total fasilitas gedung, ruangan, dan nilai aset lintas seluruh unit lembaga.</p>
            </div>
        </div>

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Total Gedung</p>
                <p class="font-display text-2xl font-bold text-gray-900 mt-1">{{ $totalGedung }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Seluruh Kampus</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Total Ruangan</p>
                <p class="font-display text-2xl font-bold text-gray-900 mt-1">{{ $totalRuangan }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Kelas, Lab, & Fasilitas</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Total Unit Aset</p>
                <p class="font-display text-2xl font-bold text-indigo-600 mt-1">{{ number_format($totalAset, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Barang Terinventarisasi</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Estimasi Nilai Aset</p>
                <p class="font-display text-2xl font-bold text-emerald-600 mt-1">Rp {{ number_format($totalNilaiAset, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Total Harga Perolehan</p>
            </div>
        </div>

        {{-- Table Rekap Per Unit Lembaga --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h2 class="font-display text-sm font-bold text-gray-900">Distribusi Fasilitas & Nilai Aset Per Lembaga</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3.5">Nama Unit Lembaga</th>
                            <th class="px-6 py-3.5 text-center">Jenjang</th>
                            <th class="px-6 py-3.5 text-center">Jumlah Gedung</th>
                            <th class="px-6 py-3.5 text-center">Jumlah Ruangan</th>
                            <th class="px-6 py-3.5 text-center">Total Aset (Item)</th>
                            <th class="px-6 py-3.5 text-right">Total Nilai Perolehan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($rekapPerLembaga as $lem)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4 font-semibold text-gray-900">{{ $lem->nama }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-bold text-gray-700">
                                        {{ $lem->jenjang }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">{{ $lem->gedung_count }} Gedung</td>
                                <td class="px-6 py-4 text-center">{{ $lem->ruangan_count }} Ruangan</td>
                                <td class="px-6 py-4 text-center">{{ number_format($lem->total_aset_qty, 0, ',', '.') }} unit</td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-900">Rp {{ number_format($lem->total_nilai_aset, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400">Belum ada unit lembaga terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
