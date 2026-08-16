<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Sarpras Yayasan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Konsolidasi total fasilitas gedung, ruangan, dan estimasi nilai aset lintas seluruh unit lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Yayasan <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekapitulasi Global</b>
            </p>
        </div>

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="apartment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Gedung</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalGedung }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Seluruh Unit</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="meeting_room" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Total Ruangan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalRuangan }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Fasilitas</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="inventory_2" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Total Unit Aset</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($totalAset, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Barang</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="payments" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Estimasi Nilai Aset</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight">Rp {{ number_format($totalNilaiAset, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Rekap Per Unit Lembaga --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <p class="font-display text-sm font-bold text-gray-900">Distribusi Fasilitas & Nilai Aset Per Lembaga</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
                            <th class="px-5 py-3">Nama Unit Lembaga</th>
                            <th class="px-5 py-3 text-center">Jenjang</th>
                            <th class="px-5 py-3 text-center">Jumlah Gedung</th>
                            <th class="px-5 py-3 text-center">Jumlah Ruangan</th>
                            <th class="px-5 py-3 text-center">Total Aset</th>
                            <th class="px-5 py-3 text-right">Total Nilai Perolehan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($rekapPerLembaga as $lem)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $lem->nama }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <x-badge tone="slate">{{ $lem->jenjang }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5 text-center text-gray-600">{{ $lem->gedung_count }} Gedung</td>
                                <td class="px-5 py-3.5 text-center text-gray-600">{{ $lem->ruangan_count }} Ruangan</td>
                                <td class="px-5 py-3.5 text-center font-semibold text-gray-900">{{ number_format($lem->total_aset_qty, 0, ',', '.') }} unit</td>
                                <td class="px-5 py-3.5 text-right font-semibold text-emerald-600">Rp {{ number_format($lem->total_nilai_aset, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-gray-400">Belum ada unit lembaga terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
