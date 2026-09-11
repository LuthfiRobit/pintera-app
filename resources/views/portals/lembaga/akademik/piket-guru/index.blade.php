@php
    $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
@endphp

<x-app-layout>
    <div
        class="mx-auto max-w-6xl space-y-4"
        x-data="{
            activeTab: 'mingguan',
            ...dataTableFilter({
                filters: {
                    search: @js($filters['search'] ?? ''),
                    hari: @js($filters['hari'] !== null && $filters['hari'] !== '' ? (string) $filters['hari'] : ''),
                    semester_id: @js($filters['semester_id'] ?? ''),
                    lembaga_id: @js($filters['lembaga_id'] ?? '')
                },
                perPage: 20,
                indexUrlBase: @js(route('admin.piket-guru.index'))
            })
        }"
    >
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Piket Guru</h1>
                    <x-scope-badge :is-yayasan="$isYayasan ?? false" :active-lembaga="$activeLembaga ?? null" />
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Kelola jadwal piket mingguan guru, pemantauan kalender piket harian, dan override penugasan khusus.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Piket</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="calendar_month" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Jadwal</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $stats['totalJadwal'] ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Mingguan</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Guru Bertugas</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $stats['guruTerjadwal'] ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Terdaftar</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="edit_calendar" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Override Aktif</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $stats['overrideAktif'] ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Mendatang</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="{{ ($isYayasanAggregate ?? false) ? 'apartment' : 'date_range' }}" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">{{ ($isYayasanAggregate ?? false) ? 'Unit Terjadwal' : 'Hari Tercover' }}</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $stats['lembagaTerjadwal'] ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">{{ ($isYayasanAggregate ?? false) ? 'Lembaga' : 'Hari' }}</span>
            </div>
        </div>

        {{-- Filter Card (Standar admin/kelas) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <button
                        type="button"
                        x-show="filters.search || filters.hari !== '' || filters.semester_id || filters.lembaga_id"
                        @click="filters.search = ''; filters.hari = ''; filters.semester_id = ''; filters.lembaga_id = ''; for (let key in tomSelects) { if (tomSelects[key]) tomSelects[key].clear(true); } muatUlangDaftar();"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800 transition"
                    >
                        <x-icon name="close" class="h-3.5 w-3.5" />
                        Reset Filter
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    @if (! ($isYayasanAggregate ?? false))
                        <x-link-button href="{{ route('admin.piket-guru.create') }}">
                            <span class="text-base leading-none">+</span> Tambah Jadwal
                        </x-link-button>
                    @else
                        <x-tooltip text="Pilih 1 lembaga aktif melalui pengalih lembaga di atas untuk menambah jadwal piket.">
                            <x-secondary-button type="button" disabled class="cursor-not-allowed opacity-60">
                                <span class="text-base leading-none">+</span> Tambah Jadwal
                            </x-secondary-button>
                        </x-tooltip>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 {{ ($isYayasanAggregate ?? false) ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
                {{-- Search Guru --}}
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text"
                            x-model="filters.search"
                            @input.debounce.500ms="muatUlangDaftar()"
                            placeholder="Nama guru piket"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                {{-- Filter Hari --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Hari Bertugas</label>
                    <select x-ref="hariSelect" x-init="initFilterSelect($refs.hariSelect, 'hari', false)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Hari</option>
                        @foreach ($namaHari as $idx => $labelHari)
                            <option value="{{ $idx }}" @selected(($filters['hari'] !== null && $filters['hari'] !== '') && (int) $filters['hari'] === $idx)>
                                {{ $labelHari }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Semester --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Semester</label>
                    <select x-ref="semSelect" x-init="initFilterSelect($refs.semSelect, 'semester_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Semester</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected(($filters['semester_id'] ?? null) == $sem->id)>
                                {{ $sem->tahunAjaran->nama ?? '' }} - {{ $sem->nama }}{{ ($isYayasanAggregate && $sem->lembaga) ? ' ('.$sem->lembaga->nama.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Lembaga (Hanya saat agregat yayasan) --}}
                @if ($isYayasanAggregate ?? false)
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Unit Lembaga</label>
                        <select x-ref="lemSelect" x-init="initFilterSelect($refs.lemSelect, 'lembaga_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Lembaga</option>
                            @foreach ($lembagaList as $lem)
                                <option value="{{ $lem->id }}" @selected(($filters['lembaga_id'] ?? null) == $lem->id)>
                                    {{ $lem->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        {{-- AJAX Table Container --}}
        <div x-ref="tableContainer">
            @include('portals.lembaga.akademik.piket-guru._daftar')
        </div>
    </div>
</x-app-layout>
