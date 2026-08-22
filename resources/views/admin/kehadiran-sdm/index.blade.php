{{-- resources/views/admin/kehadiran-sdm/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="kehadiranSdmSPA()">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kehadiran SDM</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar &amp; rekapitulasi presensi harian seluruh pegawai (Guru &amp; Karyawan).</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM &amp; Kepegawaian <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kehadiran SDM</b>
                </p>
                <a href="{{ route('admin.kehadiran-sdm.konfigurasi.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Konfigurasi</span>
                </a>
                @can('kehadiran-sdm.catat')
                    <a href="{{ route('admin.kehadiran-sdm.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                        <span>+ Catat Kehadiran</span>
                    </a>
                @endcan
            </div>
        </div>

        {{-- Compact 3-Column Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Tercatat</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="records.length"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Pegawai</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Hadir / Tepat Waktu</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countHadir"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-emerald-600 font-semibold" x-text="percentHadir + '%'"></span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Terlambat / Dispensasi</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countTerlambatOrIzin"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-amber-600 font-semibold">Perlu Perhatian</span>
            </div>
        </div>

        {{-- Filter & Date Selection Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Date Picker Input (Seamless AJAX without page reload) --}}
                <div class="lg:col-span-4">
                    <label class="mb-1.5 flex items-center justify-between text-xs font-semibold text-gray-500">
                        <span>Pilih Tanggal Presensi</span>
                        <span x-show="loadingDate" class="text-[10px] text-brand-600 font-normal animate-pulse" style="display: none;">Memuat data...</span>
                    </label>
                    <input 
                        type="date" 
                        name="tanggal" 
                        x-model="tanggal" 
                        @change="changeDate($event.target.value)" 
                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3.5 py-2 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                    >
                </div>

                {{-- Search Pegawai Input --}}
                <div class="lg:col-span-4">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pegawai</label>
                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="searchQuery" type="text" placeholder="Ketik nama pegawai..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Quick Date Navigation (AJAX - Zero Page Reload) --}}
                <div class="lg:col-span-4 flex items-center justify-end gap-2">
                    <button 
                        @click.prevent="changeDate(dateKemarin)" 
                        type="button" 
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition active:scale-95 shadow-2xs"
                    >
                        &larr; Kemarin
                    </button>
                    <button 
                        @click.prevent="changeDate(dateHariIni)" 
                        type="button" 
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition active:scale-95 shadow-2xs"
                    >
                        Hari Ini
                    </button>
                    <button 
                        @click.prevent="changeDate(dateBesok)" 
                        type="button" 
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition active:scale-95 shadow-2xs"
                    >
                        Besok &rarr;
                    </button>
                </div>
            </div>

            {{-- Filter Pills Tabs --}}
            <div class="border-t border-gray-100 pt-3">
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                    <button 
                        @click="activeFilter = 'semua'" 
                        type="button" 
                        :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Semua Status</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="records.length"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'hadir'" 
                        type="button" 
                        :class="activeFilter === 'hadir' ? 'bg-emerald-50 font-semibold text-emerald-700 border-emerald-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Hadir Tepat Waktu</span>
                        <span :class="activeFilter === 'hadir' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countHadirOnly"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'terlambat'" 
                        type="button" 
                        :class="activeFilter === 'terlambat' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Terlambat</span>
                        <span :class="activeFilter === 'terlambat' ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countTerlambatOnly"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'izin'" 
                        type="button" 
                        :class="activeFilter === 'izin' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Izin / Sakit / Cuti</span>
                        <span :class="activeFilter === 'izin' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countIzinOnly"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="relative rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Loading Overlay --}}
            <div x-show="loadingDate" class="absolute inset-0 z-20 flex items-center justify-center bg-white/70 backdrop-blur-xs rounded-2xl" style="display: none;">
                <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 text-white text-xs font-semibold shadow-lg">
                    <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat Presensi...</span>
                </div>
            </div>

            {{-- Table Header Bar --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <h2 class="font-display text-sm font-bold text-gray-900">Rekap Presensi Harian</h2>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600" x-text="filteredRecords.length + ' Entri'"></span>
                </div>
                <p class="text-xs text-gray-400">Tanggal: <b class="text-gray-700 font-mono" x-text="formatDateIndo(tanggal)"></b></p>
            </div>

            {{-- Table Data --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Masuk</th>
                            <th class="px-5 py-3">Pulang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-if="filteredRecords.length === 0">
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700">Belum ada data kehadiran pada tanggal ini.</p>
                                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Gunakan filter di atas atau klik "+ Catat Kehadiran" untuk mencatat presensi pegawai.</p>
                                </td>
                            </tr>
                        </template>

                        <template x-for="item in filteredRecords" :key="item.id">
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                <td class="px-5 py-3.5">
                                    <span :class="item.badgeClass" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize" x-text="item.statusLabel"></span>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs font-bold text-gray-800" x-text="item.masuk"></td>
                                <td class="px-5 py-3.5 font-mono text-xs font-bold text-gray-800" x-text="item.pulang"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Inline Factory Script for Seamless AJAX Date Filter & Client-side Stats --}}
    <script>
        function kehadiranSdmSPA() {
            return {
                tanggal: @js($tanggal),
                todayStr: @js(now()->toDateString()),
                records: @js($recordList->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'nama' => $r->pegawai->nama ?? '—',
                        'status' => $r->status->value,
                        'statusLabel' => $r->status->label(),
                        'badgeTone' => $r->status->badgeTone(),
                        'badgeClass' => 'bg-' . $r->status->badgeTone() . '-100 text-' . $r->status->badgeTone() . '-800',
                        'masuk' => $r->waktu_masuk ? $r->waktu_masuk->format('H:i') : '—',
                        'pulang' => $r->waktu_pulang ? $r->waktu_pulang->format('H:i') : '—',
                    ];
                })->values()->all()),
                searchQuery: '',
                activeFilter: 'semua',
                loadingDate: false,

                get dateKemarin() {
                    return this.addDays(this.tanggal, -1);
                },

                get dateHariIni() {
                    return this.todayStr;
                },

                get dateBesok() {
                    return this.addDays(this.tanggal, 1);
                },

                addDays(dateStr, days) {
                    const parts = dateStr.split('-');
                    const d = new Date(parts[0], parts[1] - 1, parts[2]);
                    d.setDate(d.getDate() + days);
                    const year = d.getFullYear();
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                },

                formatDateIndo(dateStr) {
                    if (!dateStr) return '';
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    const parts = dateStr.split('-');
                    if (parts.length !== 3) return dateStr;
                    return `${parseInt(parts[2])} ${months[parseInt(parts[1]) - 1]} ${parts[0]}`;
                },

                async changeDate(targetDate) {
                    if (!targetDate || this.loadingDate) return;
                    this.loadingDate = true;
                    this.tanggal = targetDate;

                    try {
                        const baseUrl = @js(route('admin.kehadiran-sdm.index'));
                        const res = await fetch(`${baseUrl}?tanggal=${targetDate}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        
                        const html = await res.text();
                        
                        // Extract embedded records array from HTML response
                        const scriptMatch = html.match(/records:\s*(\[[\s\S]*?\]),\s*searchQuery/);
                        if (scriptMatch && scriptMatch[1]) {
                            this.records = JSON.parse(scriptMatch[1]);
                        } else {
                            // Fallback DOM parse if regex fails
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const scriptElem = Array.from(doc.querySelectorAll('script')).find(s => s.textContent.includes('records:'));
                            if (scriptElem) {
                                const m = scriptElem.textContent.match(/records:\s*(\[[\s\S]*?\]),\s*searchQuery/);
                                if (m && m[1]) this.records = JSON.parse(m[1]);
                            }
                        }

                        // Update browser URL query string without page reload
                        window.history.pushState({ tanggal: targetDate }, '', `${baseUrl}?tanggal=${targetDate}`);
                    } catch (e) {
                        console.error('Gagal memuat presensi tanggal:', e);
                        if (this.$store && this.$store.toast) {
                            this.$store.toast.push('error', 'Gagal memuat data presensi.');
                        }
                    } finally {
                        this.loadingDate = false;
                    }
                },

                get filteredRecords() {
                    return this.records.filter(r => {
                        const matchSearch = r.nama.toLowerCase().includes(this.searchQuery.toLowerCase());
                        if (this.activeFilter === 'semua') return matchSearch;
                        if (this.activeFilter === 'hadir') return matchSearch && r.status === 'hadir';
                        if (this.activeFilter === 'terlambat') return matchSearch && r.status === 'terlambat';
                        if (this.activeFilter === 'izin') return matchSearch && ['izin', 'sakit', 'cuti'].includes(r.status);
                        return matchSearch;
                    });
                },

                get countHadir() {
                    return this.records.filter(r => ['hadir', 'terlambat'].includes(r.status)).length;
                },

                get countHadirOnly() {
                    return this.records.filter(r => r.status === 'hadir').length;
                },

                get countTerlambatOnly() {
                    return this.records.filter(r => r.status === 'terlambat').length;
                },

                get countIzinOnly() {
                    return this.records.filter(r => ['izin', 'sakit', 'cuti'].includes(r.status)).length;
                },

                get countTerlambatOrIzin() {
                    return this.records.filter(r => ['terlambat', 'izin', 'sakit', 'cuti', 'alpa'].includes(r.status)).length;
                },

                get percentHadir() {
                    if (this.records.length === 0) return 0;
                    return Math.round((this.countHadir / this.records.length) * 100);
                }
            }
        }
    </script>
</x-app-layout>
