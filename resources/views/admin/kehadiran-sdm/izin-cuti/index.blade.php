{{-- resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="approvalIzinCutiSPA()">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Persetujuan Izin/Cuti SDM</h1>
                <p class="text-xs text-gray-500 mt-0.5">Review &amp; proses persetujuan permohonan izin, sakit, dan cuti pegawai.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM &amp; Kepegawaian <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Persetujuan Izin/Cuti</b>
            </p>
        </div>

        {{-- Compact 3-Column Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Approval</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                    </div>
                </div>
                <span class="text-[11px] font-semibold text-amber-600">Perlu Tindakan</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-blue-600">Permohonan Cuti</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countCuti"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Tahap Bertingkat</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-rose-600">Izin &amp; Sakit</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countDispensasi"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Dispensasi</span>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Search Input --}}
                <div class="lg:col-span-6">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pegawai / Alasan</label>
                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="searchQuery" type="text" placeholder="Ketik nama pegawai..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Pill Tabs Filters --}}
                <div class="lg:col-span-6 flex items-center justify-start lg:justify-end gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                    <button 
                        @click="activeFilter = 'semua'" 
                        type="button" 
                        :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Semua</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'cuti'" 
                        type="button" 
                        :class="activeFilter === 'cuti' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Cuti</span>
                        <span :class="activeFilter === 'cuti' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countCuti"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'sakit'" 
                        type="button" 
                        :class="activeFilter === 'sakit' ? 'bg-rose-50 font-semibold text-rose-700 border-rose-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Sakit</span>
                        <span :class="activeFilter === 'sakit' ? 'bg-rose-100 text-rose-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countSakit"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'izin'" 
                        type="button" 
                        :class="activeFilter === 'izin' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Izin</span>
                        <span :class="activeFilter === 'izin' ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countIzin"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Table Header Bar --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <h2 class="font-display text-sm font-bold text-gray-900">Daftar Pengajuan Masuk</h2>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200" x-text="filteredItems.length + ' Menunggu'"></span>
                </div>
                <p class="text-xs text-gray-400">Klik tombol Review untuk memberi persetujuan atau penolakan</p>
            </div>

            {{-- Table Data --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3 w-28 text-center">Aksi</th>
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Periode Tanggal</th>
                            <th class="px-5 py-3">Langkah Saat Ini</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-if="filteredItems.length === 0">
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700">Tidak ada pengajuan menunggu persetujuan.</p>
                                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Seluruh pengajuan izin/cuti pegawai telah selesai diproses.</p>
                                </td>
                            </tr>
                        </template>

                        <template x-for="item in filteredItems" :key="item.id">
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 text-center">
                                    <div class="relative group inline-block">
                                        <a 
                                            :href="item.showUrl" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Review</span>
                                        </a>
                                        <span class="absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none shadow-md">
                                            Review &amp; Diproses
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                <td class="px-5 py-3.5">
                                    <span :class="item.kategoriClass" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold" x-text="item.kategoriLabel"></span>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs font-bold text-gray-800" x-text="item.periode"></td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 font-medium">
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        <span x-text="item.step"></span>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Inline SPA Factory Script --}}
    <script>
        function approvalIzinCutiSPA() {
            return {
                items: @js($daftar->map(function ($item) {
                    $k = $item->kategori->value;
                    $class = match($k) {
                        'cuti' => 'bg-blue-100 text-blue-800',
                        'sakit' => 'bg-rose-100 text-rose-800',
                        default => 'bg-amber-100 text-amber-800',
                    };
                    return [
                        'id' => $item->id,
                        'nama' => $item->pegawai->nama ?? '—',
                        'kategori' => $k,
                        'kategoriLabel' => $item->kategori->label(),
                        'kategoriClass' => $class,
                        'periode' => $item->tanggal_mulai->format('d M Y') . ' — ' . $item->tanggal_selesai->format('d M Y'),
                        'step' => $item->approvalRequest?->currentStep?->step_name ?? '—',
                        'showUrl' => route('admin.kehadiran-sdm.izin-cuti.show', $item),
                    ];
                })->values()->all()),
                searchQuery: '',
                activeFilter: 'semua',

                get filteredItems() {
                    return this.items.filter(item => {
                        const matchSearch = item.nama.toLowerCase().includes(this.searchQuery.toLowerCase());
                        if (this.activeFilter === 'semua') return matchSearch;
                        return matchSearch && item.kategori === this.activeFilter;
                    });
                },

                get countCuti() {
                    return this.items.filter(i => i.kategori === 'cuti').length;
                },

                get countSakit() {
                    return this.items.filter(i => i.kategori === 'sakit').length;
                },

                get countIzin() {
                    return this.items.filter(i => i.kategori === 'izin').length;
                },

                get countDispensasi() {
                    return this.items.filter(i => ['izin', 'sakit'].includes(i.kategori)).length;
                }
            }
        }
    </script>
</x-app-layout>
