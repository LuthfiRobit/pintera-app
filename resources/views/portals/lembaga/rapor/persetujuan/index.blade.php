<x-app-layout>
    <div
        class="mx-auto max-w-6xl space-y-5"
        x-data="persetujuanRaporFilter({
            tab: @js($tab),
            tahunAjaranId: @js(request('tahun_ajaran_id', '')),
            semesterId: @js(request('semester_id', '')),
            search: @js(request('search', '')),
            stats: @js($stats),
            opsiUrl: @js(route('admin.rapor.persetujuan.opsi')),
            indexUrl: @js(route('admin.rapor.persetujuan.index')),
        })"
    >
        {{-- Flash Messages & Toast Notifications --}}
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-medium text-success-800 shadow-xs flex items-center gap-2.5" x-data>
                <x-icon name="check_circle" class="h-5 w-5 text-success-600 shrink-0" />
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-xs flex items-center gap-2.5" x-data x-init="$store.toast.push('error', @js($errors->first()))">
                <x-icon name="warning" class="h-5 w-5 text-error-600 shrink-0" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- Header & Scope Badge --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold text-gray-900">Persetujuan Rapor</h1>
                    <x-scope-badge :is-yayasan="$isYayasan ?? false" :active-lembaga="$activeLembaga ?? null" />
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Daftar pengajuan rapor kelas yang membutuhkan telaah, verifikasi, atau keputusan pengesahan Anda sebagai <b class="font-semibold text-gray-700">{{ $stats['roleAktor'] ?? 'Pengambil Keputusan' }}</b>.
                </p>
            </div>
            <p class="text-xs text-gray-400 shrink-0">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-600">Persetujuan Rapor</b>
            </p>
        </div>

        {{-- 4 KPI STATS CARDS --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {{-- 1. Menunggu Keputusan --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Tindakan</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900" x-text="stats.totalMenunggu ?? 0">{{ $stats['totalMenunggu'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600">
                        <x-icon name="checklist" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">Perlu keputusan Anda segera</p>
            </div>

            {{-- 2. Total Disetujui --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Telah Disetujui</p>
                        <p class="mt-1 font-display text-2xl font-bold text-emerald-700" x-text="stats.totalDisetujui ?? 0">{{ $stats['totalDisetujui'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-emerald-600 font-medium">Siap cetak &amp; bagikan</p>
            </div>

            {{-- 3. Perlu Revisi / Ditolak --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-600">Perlu Revisi</p>
                        <p class="mt-1 font-display text-2xl font-bold text-rose-700" x-text="stats.totalDitolak ?? 0">{{ $stats['totalDitolak'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-2.5 text-rose-600">
                        <x-icon name="warning" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">Dikembalikan ke wali kelas</p>
            </div>

            {{-- 4. Total Riwayat Keputusan --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Total Riwayat</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900" x-text="stats.totalRiwayat ?? 0">{{ $stats['totalRiwayat'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-2.5 text-brand-600">
                        <x-icon name="apartment" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">Arsip persetujuan semester</p>
            </div>
        </div>

        {{-- FILTER & CONTROLS TOOLBAR (Searchable TomSelect Standar Pintera, matching TP Page) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            {{-- Header of Filter --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3.5">
                <div>
                    <h3 class="font-display text-sm font-bold text-gray-900">Filter &amp; Pencarian Pengajuan Rapor</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Filter otomatis diperbarui secara instan tanpa reload halaman.</p>
                </div>
                <button
                    type="button"
                    x-show="tahunAjaranId || semesterId || search"
                    @click="resetFilters()"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-800 transition self-start sm:self-auto"
                >
                    <x-icon name="close" class="h-3.5 w-3.5" />
                    <span>Reset Semua Filter</span>
                </button>
            </div>

            {{-- 2 Searchable TomSelect Filters --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                {{-- 1. Tahun Ajaran (Searchable TomSelect) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Tahun Ajaran</label>
                    <select
                        x-ref="tahunAjaranSelect"
                        x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected(request('tahun_ajaran_id') == $tahunAjaran->id)>
                                {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 2. Semester (Searchable TomSelect) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Semester</label>
                    <select
                        x-ref="semesterSelect"
                        x-init="initSemesterSelect($refs.semesterSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Semester</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected(request('semester_id') == $sem->id)>
                                {{ $sem->nama }} ({{ $sem->tahunAjaran->nama ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Realtime Search Bar --}}
            <div class="relative pt-1">
                <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    x-model="search"
                    @input.debounce.300ms="muatUlangDaftar()"
                    placeholder="Ketik untuk mencari kelas, wali kelas, atau pengaju (respons instan)..."
                    class="w-full rounded-xl border-gray-200 pl-10 pr-10 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500 py-2.5 shadow-sm"
                >
                <button
                    type="button"
                    x-show="search"
                    @click="search = ''; muatUlangDaftar()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                    <x-icon name="close" class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Controls Row: Status Filter Tabs (Segmented Pill Tab Style matching TP) --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-3.5 shadow-card">
            {{-- Status Filter Tabs --}}
            <div class="inline-flex rounded-xl bg-gray-100 p-1 text-xs font-semibold">
                <button
                    type="button"
                    @click="setTab('menunggu')"
                    :class="tab === 'menunggu' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="rounded-lg px-3.5 py-1.5 transition flex items-center gap-1.5"
                >
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>Menunggu Keputusan Saya (<span x-text="stats.totalMenunggu ?? 0">{{ $stats['totalMenunggu'] ?? 0 }}</span>)</span>
                </button>
                <button
                    type="button"
                    @click="setTab('riwayat')"
                    :class="tab === 'riwayat' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="rounded-lg px-3.5 py-1.5 transition flex items-center gap-1.5"
                >
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>Riwayat Keputusan (<span x-text="stats.totalRiwayat ?? 0">{{ $stats['totalRiwayat'] ?? 0 }}</span>)</span>
                </button>
            </div>

            {{-- Quick Info on Active Tab --}}
            <div class="text-xs text-gray-500">
                <span x-show="tab === 'menunggu'" class="inline-flex items-center gap-1.5 font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Daftar kelas yang menunggu keputusan Anda
                </span>
                <span x-show="tab === 'riwayat'" class="inline-flex items-center gap-1.5 font-medium text-gray-600 bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-200">
                    <x-icon name="apartment" class="h-3.5 w-3.5 text-gray-400" />
                    Arsip pengajuan yang telah diputuskan
                </span>
            </div>
        </div>

        {{-- CONTENT CONTAINER (KPI Stats + Table, updated via AJAX without reload) --}}
        <div class="relative" x-ref="daftarContainer">
            {{-- Loading Shimmer Overlay (Matches TP Page) --}}
            <div
                x-show="isLoading"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/70 backdrop-blur-xs min-h-[250px]"
            >
                <div class="flex items-center gap-2.5 rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-semibold text-white shadow-xl">
                    <svg class="h-4 w-4 animate-spin text-brand-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memperbarui data...</span>
                </div>
            </div>

            <div x-ref="tableContent">
                @include('portals.lembaga.rapor.persetujuan._daftar', ['pengajuanList' => $pengajuanList, 'tab' => $tab, 'stats' => $stats])
            </div>
        </div>
    </div>
</x-app-layout>
