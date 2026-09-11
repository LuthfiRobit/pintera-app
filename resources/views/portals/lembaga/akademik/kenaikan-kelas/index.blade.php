@php use App\Domains\Akademik\Enums\BentukPendidikan; @endphp
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-medium text-success-700 shadow-2xs" x-data>{{ session('status') }}</div>
        @endif
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-medium text-success-700 shadow-2xs" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-2xs" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold text-gray-900">Kenaikan &amp; Kelulusan Kelas</h1>
                    <x-scope-badge :is-yayasan="$isYayasan ?? false" :active-lembaga="$activeLembaga ?? null" />
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Petakan perpindahan kelas dan kelulusan siswa antar tahun ajaran beserta penyalinan jadwal pelajaran.
                </p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kenaikan Kelas</b>
            </p>
        </div>

        {{-- Parameter Filter Card: Source & Target Tahun Ajaran --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                        <x-icon name="tune" class="h-4 w-4" />
                    </span>
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Parameter Tahun Ajaran</h2>
                        <p class="text-xs text-gray-500">Tentukan tahun ajaran sumber (lama) dan tahun ajaran tujuan (baru) untuk memuat kelas.</p>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.kenaikan-kelas.index') }}" class="space-y-4"
                  x-data="{
                      initFilterSelect(el, placeholder) {
                          if (!window.TomSelect || el.tomselect) return;
                          new window.TomSelect(el, {
                              maxItems: 1,
                              create: false,
                              placeholder: placeholder,
                              allowEmptyOption: true,
                          });
                      }
                  }">
                <div class="grid grid-cols-1 items-center gap-3 md:grid-cols-11">
                    {{-- 1. Tahun Ajaran Sumber (5 cols) --}}
                    <div class="md:col-span-5">
                        <x-input-label for="tahun_ajaran_id" value="Tahun Ajaran Sumber (Kelas Lama)" class="mb-1.5" />
                        <x-select id="tahun_ajaran_id" name="tahun_ajaran_id" x-init="$nextTick(() => initFilterSelect($el, '— Pilih Tahun Ajaran Sumber —'))">
                            <option value="">— Pilih Tahun Ajaran Sumber —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
                                    {{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>

                    {{-- Panah Konektor Visual (1 col) --}}
                    <div class="hidden md:flex md:col-span-1 items-center justify-center pt-5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <x-icon name="arrow_forward" class="h-4 w-4" />
                        </span>
                    </div>

                    {{-- 2. Tahun Ajaran Tujuan (5 cols) --}}
                    <div class="md:col-span-5">
                        <x-input-label for="tahun_ajaran_tujuan_id" value="Tahun Ajaran Tujuan (Kelas Baru)" class="mb-1.5" />
                        <x-select id="tahun_ajaran_tujuan_id" name="tahun_ajaran_tujuan_id" x-init="$nextTick(() => initFilterSelect($el, '— Pilih Tahun Ajaran Tujuan —'))">
                            <option value="">— Pilih Tahun Ajaran Tujuan —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranTujuanId == $tahunAjaran->id)>
                                    {{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                    @if ($tahunAjaranId || $tahunAjaranTujuanId)
                        <a href="{{ route('admin.kenaikan-kelas.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-gray-700 px-3 py-2 transition">
                            ✕ Reset Pilihan
                        </a>
                    @endif
                    <x-primary-button type="submit" class="shadow-sm">
                        <x-icon name="search" class="h-4 w-4 mr-1.5" />
                        Tampilkan Data
                    </x-primary-button>
                </div>
            </form>
        </div>

        {{-- Validation / Notification Alerts --}}
        @if ($errorTahunAjaran)
            <div class="flex items-start gap-3 rounded-2xl border border-error-200 bg-error-50/70 p-4 text-sm text-error-800 shadow-2xs">
                <x-icon name="error" class="h-5 w-5 text-error-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-error-900">Perhatian Parameter Tahun Ajaran</p>
                    <p class="mt-0.5 text-xs text-error-700">{{ $errorTahunAjaran }}</p>
                </div>
            </div>
        @endif

        @if ($kelasLamaList->isNotEmpty() && $tahunAjaranTujuanId === null)
            <div class="flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50/70 p-4 text-sm text-brand-800 shadow-2xs">
                <x-icon name="info" class="h-5 w-5 text-brand-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-brand-900">Langkah Berikutnya: Pilih Tahun Ajaran Tujuan</p>
                    <p class="mt-0.5 text-xs text-brand-700">Pilih juga <b>Tahun Ajaran Tujuan (kelas baru)</b> pada form di atas, lalu klik <b>Tampilkan Data</b> untuk membuka pilihan kelas dan semester tujuan.</p>
                </div>
            </div>
        @endif

        {{-- Empty / Initial State Onboarding Guidance --}}
        @if ($tahunAjaranId === null || ($kelasLamaList->isEmpty() && ! $errorTahunAjaran))
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-card space-y-4">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                    <x-icon name="swap_horiz" class="h-7 w-7" />
                </div>
                <div>
                    <h3 class="font-display text-base font-bold text-gray-900">Alur Kenaikan &amp; Kelulusan Kelas</h3>
                    <p class="mx-auto mt-1 max-w-md text-xs text-gray-500">
                        Ikuti 3 tahapan berikut untuk memproses perpindahan kelas siswa dan penerbitan tagihan tahun ajaran baru.
                    </p>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 text-left sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">1</span>
                        <h4 class="mt-3 font-display text-sm font-semibold text-gray-900">Pilih Tahun Ajaran</h4>
                        <p class="mt-1 text-xs text-gray-500">Pilih tahun ajaran lama sebagai sumber kelas dan tahun ajaran baru sebagai tujuan.</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">2</span>
                        <h4 class="mt-3 font-display text-sm font-semibold text-gray-900">Petakan Tindakan &amp; Jadwal</h4>
                        <p class="mt-1 text-xs text-gray-500">Tentukan apakah kelas naik atau lulus. Sistem otomatis mendeteksi kesesuaian kurikulum dan tingkat.</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">3</span>
                        <h4 class="mt-3 font-display text-sm font-semibold text-gray-900">Eksekusi Aman</h4>
                        <p class="mt-1 text-xs text-gray-500">Akun siswa lulus dinonaktifkan otomatis, dan tagihan SPP baru diterbitkan untuk siswa yang naik.</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- KPI STATS PILLARS (Tampil jika daftar kelas lama ada) --}}
        @if ($kelasLamaList->isNotEmpty())
            @php
                $totalKelas = $kelasLamaList->count();
                $totalSiswa = $kelasLamaList->sum('siswa_count');
                $kelasTingkatAkhirCount = $kelasLamaList->filter(function($k) {
                    return $k->lembaga ? BentukPendidikan::from($k->lembaga->bentuk_pendidikan)->isTingkatAkhir($k->tingkat) : false;
                })->count();
                $kelasKosongCount = $kelasLamaList->where('siswa_count', 0)->count();
            @endphp

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Total Kelas</p>
                            <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $totalKelas }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-100 p-2.5 text-gray-600">
                            <x-icon name="domain" class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-gray-500">Kelas di tahun sumber</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-blue-600">Siswa Terdampak</p>
                            <p class="mt-1 font-display text-2xl font-bold text-blue-700">{{ number_format($totalSiswa, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-xl bg-blue-50 p-2.5 text-blue-600">
                            <x-icon name="groups" class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-blue-600 font-medium">Total siswa aktif</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Tingkat Akhir</p>
                            <p class="mt-1 font-display text-2xl font-bold text-amber-700">{{ $kelasTingkatAkhirCount }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600">
                            <x-icon name="school" class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-amber-600 font-medium">Direkomendasikan Lulus</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Sudah Kosong</p>
                            <p class="mt-1 font-display text-2xl font-bold text-emerald-700">{{ $kelasKosongCount }}</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600">
                            <x-icon name="check_circle" class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-emerald-600 font-medium">Otomatis dilewati</p>
                </div>
            </div>
        @endif

        {{-- PEMETAAN TABLE CARD --}}
        @if ($kelasLamaList->isNotEmpty() && $tahunAjaranTujuanId !== null)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}" x-data="kenaikanKelasForm()" @submit.prevent="konfirmasiDanKirim($event)" @change="hitungRingkasan()">
                    @csrf

                    {{-- Toolbar Card Header --}}
                    <div class="border-b border-gray-100 bg-white px-6 py-4 space-y-3.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 class="font-display text-sm font-bold text-gray-900">Pemetaan Kenaikan Kelas</h3>
                                <p class="mt-0.5 text-xs text-gray-500">Tentukan tindakan untuk setiap kelas lama: naikkan ke kelas tujuan, atau luluskan.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    id="btn-rekomendasi-otomatis"
                                    @click="terapkanRekomendasiOtomatis()"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs cursor-pointer"
                                >
                                    <x-icon name="auto_fix_high" class="h-3.5 w-3.5" />
                                    <span>Terapkan Rekomendasi Otomatis</span>
                                </button>
                            </div>
                        </div>

                        {{-- Live Search & Quick Toggle Bar --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1 border-t border-gray-50">
                            <div class="relative flex-1 max-w-sm">
                                <x-icon name="search" class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="text"
                                    x-model="searchQuery"
                                    placeholder="Cari nama kelas lama..."
                                    class="w-full rounded-lg border-gray-200 pl-9 pr-8 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500 py-1.5 shadow-2xs"
                                >
                                <button
                                    type="button"
                                    x-show="searchQuery"
                                    @click="searchQuery = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 hover:text-gray-600"
                                >
                                    ✕
                                </button>
                            </div>

                            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    x-model="semuaSalinJadwal"
                                    @change="toggleSemuaSalinJadwal()"
                                    class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                >
                                <span>Centang Semua Salin Jadwal</span>
                            </label>
                        </div>
                    </div>

                    {{-- Data Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[850px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50/75 text-xs uppercase font-bold tracking-wider text-gray-600">
                                    <th class="px-5 py-3.5">Kelas Lama</th>
                                    <th class="px-4 py-3.5 text-center">Jml Siswa</th>
                                    <th class="px-4 py-3.5 min-w-[160px]">Tindakan</th>
                                    <th class="px-4 py-3.5 min-w-[240px]">Kelas Tujuan</th>
                                    <th class="px-4 py-3.5 min-w-[220px]">
                                        <div class="flex items-center gap-1.5">
                                            <span>Salin Jadwal ke Semester</span>
                                            <x-tooltip position="bottom" as-button text="Menyalin struktur jadwal pelajaran kelas lama ke kelas tujuan pada semester yang dipilih.">
                                                <x-icon name="help_outline" class="h-3.5 w-3.5 text-gray-400" />
                                            </x-tooltip>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($kelasLamaList as $kelasLama)
                                    @php
                                        $isTingkatAkhir = $kelasLama->lembaga
                                            ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->isTingkatAkhir($kelasLama->tingkat)
                                            : false;
                                        $validTingkat = $kelasLama->lembaga
                                            ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues()
                                            : [];
                                    @endphp
                                    <tr data-kelas-lama="{{ $kelasLama->id }}"
                                        data-tingkat="{{ $kelasLama->tingkat }}"
                                        data-is-tingkat-akhir="{{ $isTingkatAkhir ? '1' : '0' }}"
                                        data-is-kosong="{{ $kelasLama->siswa_count === 0 ? '1' : '0' }}"
                                        :data-warning="((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) ? '1' : '0'"
                                        :class="{ 'border-l-4 border-amber-400 bg-amber-50/25': ((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) }"
                                        class="transition hover:bg-gray-50/60"
                                        x-show="!searchQuery || ($el.querySelector('td')?.textContent.toLowerCase().includes(searchQuery.toLowerCase().trim()))"
                                        x-data="{
                                            kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                            kurikulumAsalLabel: {{ Js::from($kelasLama->kurikulum?->label()) }},
                                            kurikulumTujuan: null,
                                            kurikulumTujuanLabel: null,
                                            tingkatTujuan: null,
                                            tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
                                            daftarTingkat: {{ Js::from($validTingkat) }},
                                            onKelasTujuanChange(event) {
                                                const opt = event.target.selectedOptions ? event.target.selectedOptions[0] : null;
                                                this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                                this.kurikulumTujuanLabel = opt?.dataset.kurikulumLabel || null;
                                                this.tingkatTujuan = opt?.dataset.tingkat || null;
                                            },
                                            get selisihIndexTingkat() {
                                                if (this.tingkatTujuan === null || this.tingkatAsal === null) return null;
                                                const indexAsal = this.daftarTingkat.indexOf(this.tingkatAsal);
                                                const indexTujuan = this.daftarTingkat.indexOf(this.tingkatTujuan);
                                                if (indexAsal === -1 || indexTujuan === -1) return null;
                                                return indexTujuan - indexAsal;
                                            },
                                        }"
                                    >
                                        {{-- 1. Kelas Lama --}}
                                        <td class="px-5 py-3.5 font-bold text-gray-900" data-nama="{{ $kelasLama->nama }}">
                                            {{ $kelasLama->nama }}
                                            @if ($kelasLama->siswa_count === 0)
                                                <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Sudah diproses / kosong</span>
                                            @endif
                                            <span class="text-xs font-normal text-gray-400">(Tingkat {{ $kelasLama->tingkat ?? '-' }})</span>
                                        </td>

                                        {{-- 2. Jumlah Siswa --}}
                                        <td class="px-4 py-3.5 text-center">
                                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-100 px-2 text-xs font-semibold text-gray-700">{{ $kelasLama->siswa_count }}</span>
                                        </td>

                                        {{-- 3. Tindakan --}}
                                        <td class="px-4 py-3.5">
                                            <x-select name="mapping[{{ $kelasLama->id }}][tindakan]" x-init="window.initRowSelect && window.initRowSelect($el)" class="text-xs">
                                                <option value="lewati" @selected($kelasLama->siswa_count === 0)>Lewati{{ $kelasLama->siswa_count === 0 ? ' (sudah kosong)' : '' }}</option>
                                                <option value="naik" @selected(! $isTingkatAkhir && $kelasLama->siswa_count > 0)>Naik Kelas</option>
                                                <option value="lulus" @selected($isTingkatAkhir && $kelasLama->siswa_count > 0)>Lulus</option>
                                            </x-select>
                                            @if ($isTingkatAkhir)
                                                <p class="mt-1 text-[11px] font-medium text-amber-600 flex items-center gap-1">
                                                    <x-icon name="info" class="h-3 w-3 inline shrink-0" />
                                                    Disarankan: tingkat akhir jenjang
                                                </p>
                                            @endif
                                        </td>

                                        {{-- 4. Kelas Tujuan --}}
                                        <td class="px-4 py-3.5">
                                            <x-select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" x-init="window.initRowSelect && window.initRowSelect($el, true, '— Pilih Kelas Tujuan —')" x-on:change="onKelasTujuanChange($event)" class="w-full text-xs">
                                                <option value="">— Pilih Kelas Tujuan —</option>
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-kurikulum-label="{{ $kelasBaru->kurikulum?->label() }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
                                            </x-select>

                                            {{-- Warnings & Info Callouts --}}
                                            <div class="mt-1.5 space-y-1">
                                                <p x-show="tingkatTujuan !== null" class="text-[11px] text-gray-500" x-text="'Tingkat tujuan: ' + tingkatTujuan"></p>
                                                <div x-show="kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal"
                                                     class="flex items-start gap-1 text-[11px] font-medium text-amber-700 bg-amber-50 rounded-md p-1.5 border border-amber-200">
                                                    <x-icon name="warning" class="h-3.5 w-3.5 text-amber-600 shrink-0 mt-0.5" />
                                                    <span x-text="'⚠ Kurikulum berbeda: kelas asal ' + kurikulumAsalLabel + ', kelas tujuan ' + kurikulumTujuanLabel"></span>
                                                </div>
                                                <p x-show="selisihIndexTingkat === 0" class="text-[11px] text-gray-500 font-medium" x-text="'↔ Tinggal kelas: tingkat tidak berubah (' + tingkatAsal + ')'"></p>
                                                <div x-show="selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1"
                                                     class="flex items-start gap-1 text-[11px] font-medium text-amber-700 bg-amber-50 rounded-md p-1.5 border border-amber-200">
                                                    <x-icon name="warning" class="h-3.5 w-3.5 text-amber-600 shrink-0 mt-0.5" />
                                                    <span x-text="'⚠ Tingkat tidak wajar: dari tingkat ' + tingkatAsal + ' ke ' + tingkatTujuan + ' — periksa kembali pilihan kelas tujuan'"></span>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- 5. Salin Jadwal --}}
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" name="mapping[{{ $kelasLama->id }}][salin_jadwal]" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 shrink-0">
                                                <x-select name="mapping[{{ $kelasLama->id }}][semester_tujuan_id]"
                                                          x-init="window.initRowSelect && window.initRowSelect($el, false, '— Semester —')"
                                                          class="w-full text-xs">
                                                    <option value="">— Semester —</option>
                                                    @foreach ($semesterList as $semester)
                                                        <option value="{{ $semester->id }}">{{ $semester->nama }}</option>
                                                    @endforeach
                                                </x-select>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Sticky / Elevated Submission Bar with Realtime Summary --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-gray-200 bg-gray-50/75 px-6 py-4">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-semibold text-gray-500 mr-1">Ringkasan:</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700 border border-blue-200 shadow-2xs">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                                <span x-text="countNaik"></span> Kelas Naik
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700 border border-amber-200 shadow-2xs">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                                <span x-text="countLulus"></span> Kelas Lulus
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 border border-gray-200">
                                <span x-text="countLewati"></span> Dilewati
                            </span>
                            <span x-show="countPeringatan > 0" class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-700 border border-rose-200 shadow-2xs">
                                <x-icon name="warning" class="h-3.5 w-3.5 text-rose-600" />
                                <span x-text="countPeringatan"></span> Peringatan
                            </span>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <x-primary-button type="submit" x-bind:disabled="submitting" class="shadow-sm">
                                <svg x-show="submitting" class="mr-2 h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <x-icon x-show="!submitting" name="swap_horiz" class="h-4 w-4 mr-1.5" />
                                <span>Proses Kenaikan Kelas</span>
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
