<x-app-layout>
    <div
        class="mx-auto max-w-6xl space-y-5"
        x-data="raporWaliKelasFilter({
            tahunAjaranId: @js($tahunAjaranId),
            semesterId: @js($semesterId),
            kelasId: @js($kelasId),
            search: @js(request('search', '')),
            statusFilter: @js(request('status_catatan', 'all')),
            stats: @js($stats),
            opsiUrl: @js(route('guru.rapor.catatan.opsi')),
            indexUrl: @js(route('guru.rapor.catatan.index')),
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

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold text-gray-900">Rapor Wali Kelas</h1>
                    @if ($kelas)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                            <x-icon name="school" class="h-3.5 w-3.5" />
                            <span>{{ $kelas->nama }}</span>
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Kelola catatan perkembangan peserta didik, pantau kelengkapan nilai mata pelajaran, dan ajukan pengesahan rapor kelas.
                </p>
            </div>
            <p class="text-xs text-gray-400 shrink-0">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-600">Rapor Wali Kelas</b>
            </p>
        </div>

        {{-- 4 KPI STATS CARDS --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {{-- 1. Total Siswa Kelas --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-600">Total Siswa</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900" x-text="stats.totalSiswa ?? 0">{{ $stats['totalSiswa'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-2.5 text-brand-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500">Terdaftar di kelas perwalian</p>
            </div>

            {{-- 2. Catatan Lengkap --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Catatan Lengkap</p>
                        <p class="mt-1 font-display text-2xl font-bold text-emerald-700" x-text="stats.totalLengkap ?? 0">{{ $stats['totalLengkap'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500" x-text="Math.max((stats.totalSiswa ?? 0) - (stats.totalLengkap ?? 0), 0) + ' siswa perlu dilengkapi'">
                    {{ max(($stats['totalSiswa'] ?? 0) - ($stats['totalLengkap'] ?? 0), 0) }} siswa perlu dilengkapi
                </p>
            </div>

            {{-- 3. Kelengkapan Nilai Mapel --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider" :class="(stats.isNilaiComplete ?? true) ? 'text-emerald-600' : 'text-amber-600'">
                            Nilai Asesmen
                        </p>
                        <p class="mt-1 font-display text-xl font-bold" :class="(stats.isNilaiComplete ?? true) ? 'text-emerald-700' : 'text-amber-700'" x-text="(stats.isNilaiComplete ?? true) ? '100% Lengkap' : (stats.totalNilaiKosong ?? 0) + ' Nilai Kosong'">
                            {{ ($stats['isNilaiComplete'] ?? true) ? '100% Lengkap' : ($stats['totalNilaiKosong'] ?? 0) . ' Nilai Kosong' }}
                        </p>
                    </div>
                    <div class="rounded-xl p-2.5" :class="(stats.isNilaiComplete ?? true) ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">
                        <x-icon name="assessment" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500" x-text="(stats.isNilaiComplete ?? true) ? 'Semua mapel siap rapor' : 'Perlu koordinasi guru mapel'">
                    {{ ($stats['isNilaiComplete'] ?? true) ? 'Semua mapel siap rapor' : 'Perlu koordinasi guru mapel' }}
                </p>
            </div>

            {{-- 4. Status Pengajuan Rapor --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Status Alur</p>
                        <p class="mt-1 font-display text-base font-bold text-gray-900" x-text="stats.statusLabel ?? 'Draft'">
                            @if ($pengajuanRapor)
                                {{ $pengajuanRapor->status->label() }}
                            @else
                                Draft
                            @endif
                        </p>
                    </div>
                    <div class="rounded-xl bg-purple-50 p-2.5 text-purple-600">
                        <x-icon name="checklist" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-500" x-text="stats.diajukanPadaLabel ?? 'Belum diajukan'">
                    @if ($pengajuanRapor)
                        {{ $pengajuanRapor->diajukan_pada?->format('d M Y H:i') ?? 'Diajukan' }}
                    @else
                        Belum diajukan
                    @endif
                </p>
            </div>
        </div>

        {{-- Status Notification Alerts (Jika Ditolak / Diverifikasi / Nilai Kosong) --}}
        @if ($pengajuanRapor && $pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
            <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-800 shadow-xs flex items-start gap-3">
                <x-icon name="warning" class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <p class="font-bold">Pengajuan rapor kelas ini dikembalikan / perlu direvisi oleh pimpinan.</p>
                    @if ($pengajuanRapor->catatan_revisi)
                        <p class="text-xs text-rose-700 bg-white/80 p-2.5 rounded-xl border border-rose-200 font-medium">
                            <span class="font-bold text-rose-900">Catatan Pimpinan:</span> {{ $pengajuanRapor->catatan_revisi }}
                        </p>
                    @endif
                    <p class="text-xs text-rose-600">Silakan lakukan penyesuaian catatan sesuai arahan lalu ajukan ulang rapor.</p>
                </div>
            </div>
        @endif

        @if ($pengajuanRapor && in_array($pengajuanRapor->status, [\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi, \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui]))
            <div class="rounded-2xl border border-blue-200 bg-blue-50/80 p-4 text-sm text-blue-800 shadow-xs flex items-center gap-3">
                <x-icon name="info" class="h-5 w-5 text-blue-600 shrink-0" />
                <div>
                    <p class="font-semibold">Rapor kelas ini sudah berstatus "{{ $pengajuanRapor->status->label() }}" sejak {{ $pengajuanRapor->diajukan_pada?->translatedFormat('d F Y, H:i') }}. Tidak bisa diajukan ulang dari halaman ini.</p>
                    <p class="text-xs text-blue-600 mt-0.5">Pengajuan telah dikunci dan sedang dalam tahap finalisasi pengesahan.</p>
                </div>
            </div>
        @endif

        @if ($kelengkapanNilai->isNotEmpty())
            @php
                $totalSiswaBelumLengkap = $kelengkapanNilai->sum(fn ($sel) => $sel->siswaBelumLengkap->count());
            @endphp
            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-800 shadow-xs" x-data="{ bukaRincian: false }">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <x-icon name="warning" class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-bold">Perhatian: masih ada {{ $totalSiswaBelumLengkap }} nilai yang kosong di kelas ini.</p>
                            <p class="text-xs text-amber-700 mt-0.5">Anda tetap dapat mengajukan rapor kelas, namun pastikan hal ini telah dikoordinasikan dengan guru mata pelajaran terkait.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="bukaRincian = !bukaRincian"
                        class="text-xs font-semibold text-amber-800 hover:text-amber-950 underline shrink-0"
                        x-text="bukaRincian ? 'Tutup Rincian' : 'Lihat Rincian'"
                    ></button>
                </div>

                <div x-show="bukaRincian" x-collapse class="mt-3 pt-3 border-t border-amber-200/60 text-xs space-y-1.5">
                    @foreach ($kelengkapanNilai as $sel)
                        <div class="flex items-center justify-between text-amber-900 bg-white/60 px-3 py-1.5 rounded-lg">
                            <span class="font-semibold">{{ $sel->subjek->nama }}</span>
                            <span class="text-amber-800">
                                {{ $sel->siswaBelumLengkap->count() }} dari {{ $sel->totalSiswa }} siswa belum lengkap
                                ({{ $sel->siswaBelumLengkap->pluck('nama_lengkap')->join(', ') }})
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- FILTER & CONTROLS TOOLBAR (Searchable TomSelect Standar Pintera) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            {{-- Header of Filter --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3.5">
                <div>
                    <h3 class="font-display text-sm font-bold text-gray-900">Filter &amp; Pencarian Peserta Didik</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Filter otomatis diperbarui secara instan tanpa reload halaman.</p>
                </div>
                <button
                    type="button"
                    x-show="tahunAjaranId || semesterId || kelasId || search"
                    @click="resetFilters()"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-800 transition self-start sm:self-auto"
                >
                    <span>✕ Reset Semua Filter</span>
                </button>
            </div>

            {{-- 3 Searchable TomSelect Filters (Tahun Ajaran, Semester, Kelas) --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                {{-- 1. Tahun Ajaran --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Tahun Ajaran</label>
                    <select
                        x-ref="tahunAjaranSelect"
                        x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($tahunAjaranId == $ta->id)>
                                {{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 2. Semester --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Semester</label>
                    <select
                        x-ref="semesterSelect"
                        x-init="initSemesterSelect($refs.semesterSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Semester</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($semesterId == $sem->id)>
                                {{ $sem->nama }} {{ $sem->status_aktif ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 3. Kelas Perwalian --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Kelas Perwalian</label>
                    <select
                        x-ref="kelasSelect"
                        x-init="initKelasSelect($refs.kelasSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        @if ($kelasList->isEmpty())
                            <option value="">— Anda Bukan Wali Kelas —</option>
                        @else
                            @foreach ($kelasList as $k)
                                <option value="{{ $k->id }}" @selected($kelasId == $k->id)>
                                    {{ $k->nama }}
                                </option>
                            @endforeach
                        @endif
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
                    placeholder="Ketik untuk mencari nama siswa atau NIS (respons instan)..."
                    class="w-full rounded-xl border-gray-200 pl-10 pr-10 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500 py-2.5 shadow-sm"
                >
                <button
                    type="button"
                    x-show="search"
                    @click="search = ''; muatUlangDaftar()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 hover:text-gray-600 font-bold"
                >
                    ✕
                </button>
            </div>
        </div>

        {{-- Controls Row: Status Filter Tabs (Segmented Pill Tab Style matching TP) --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-3.5 shadow-card">
            {{-- Status Filter Tabs --}}
            <div class="inline-flex rounded-xl bg-gray-100 p-1 text-xs font-semibold">
                <button
                    type="button"
                    @click="setStatusFilter('all')"
                    :class="statusFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="rounded-lg px-3.5 py-1.5 transition"
                >
                    Semua Siswa (<span x-text="stats.totalSiswa ?? 0">{{ $stats['totalSiswa'] ?? 0 }}</span>)
                </button>
                <button
                    type="button"
                    @click="setStatusFilter('incomplete')"
                    :class="statusFilter === 'incomplete' ? 'bg-white text-amber-800 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="rounded-lg px-3.5 py-1.5 transition flex items-center gap-1.5"
                >
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>Perlu Dilengkapi (<span x-text="stats.totalBelumLengkap ?? 0">{{ $stats['totalBelumLengkap'] ?? 0 }}</span>)</span>
                </button>
                <button
                    type="button"
                    @click="setStatusFilter('complete')"
                    :class="statusFilter === 'complete' ? 'bg-white text-emerald-800 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="rounded-lg px-3.5 py-1.5 transition flex items-center gap-1.5"
                >
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>Catatan Lengkap (<span x-text="stats.totalLengkap ?? 0">{{ $stats['totalLengkap'] ?? 0 }}</span>)</span>
                </button>
            </div>

            {{-- Quick Info on Active Status --}}
            <div class="text-xs text-gray-500">
                @if ($kelas && $semester)
                    <span class="inline-flex items-center gap-1.5 font-medium text-gray-700 bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-200">
                        <x-icon name="apartment" class="h-3.5 w-3.5 text-gray-400" />
                        <span>Kelas {{ $kelas->nama }} — {{ $semester->nama }}</span>
                    </span>
                @else
                    <span class="text-amber-700 font-medium">Pilih kelas dan semester terlebih dahulu.</span>
                @endif
            </div>
        </div>

        {{-- CONTENT CONTAINER (Table + Submit Bar, updated via AJAX without reload) --}}
        <div class="relative" x-ref="daftarContainer">
            {{-- Loading Shimmer Overlay (Matches TP & Persetujuan Rapor) --}}
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
                    <span>Memperbarui data rapor...</span>
                </div>
            </div>

            <div x-ref="tableContent">
                @include('portals.guru.rapor.catatan._daftar', [
                    'kelas' => $kelas,
                    'semester' => $semester,
                    'siswaList' => $siswaList,
                    'pengajuanRapor' => $pengajuanRapor,
                    'kelengkapanNilai' => $kelengkapanNilai,
                    'stats' => $stats,
                ])
            </div>
        </div>
    </div>

</x-app-layout>
