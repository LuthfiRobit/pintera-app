@php
    $guruOptions = collect([['id' => '', 'nama' => '— Pilih atau cari guru —', 'subtext' => '']])
        ->concat(($guruList ?? collect())->map(fn ($g) => [
            'id' => (string) $g->id,
            'nama' => $g->nama,
            'subtext' => ($g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : ($g->jenis_ptk ? str_replace('_', ' ', ucwords($g->jenis_ptk, '_')) : ''))).($isYayasanAggregate && $g->lembaga ? ' • '.$g->lembaga->nama : ''),
        ]))
        ->values();

    $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
    $badgeHariTone = [
        0 => 'bg-rose-50 text-rose-700 border-rose-200',
        1 => 'bg-blue-50 text-blue-700 border-blue-200',
        2 => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        3 => 'bg-amber-50 text-amber-700 border-amber-200',
        4 => 'bg-teal-50 text-teal-700 border-teal-200',
        5 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        6 => 'bg-purple-50 text-purple-700 border-purple-200',
    ];
    $hasActiveFilter = !empty($filters['search']) || ($filters['hari'] !== null && $filters['hari'] !== '') || !empty($filters['semester_id']) || !empty($filters['lembaga_id']);
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="{ activeTab: 'mingguan' }">
        {{-- Flash Messages & Notifikasi --}}
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-2xl border border-success-200 bg-success-50 p-4 text-sm text-success-800 shadow-sm" x-data>
                <x-icon name="check_circle" class="h-5 w-5 shrink-0 text-success-600" />
                <p class="font-medium">{{ session('status') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="flex items-center gap-3 rounded-2xl border border-error-200 bg-error-50 p-4 text-sm text-error-800 shadow-sm" x-data x-init="$store.toast.push('error', @js($errors->first()))">
                <x-icon name="error" class="h-5 w-5 shrink-0 text-error-600" />
                <p class="font-medium">{{ $errors->first() }}</p>
            </div>
        @endif

        {{-- Header & Scope Context --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">Jadwal Piket Guru</h1>
                    <x-scope-badge :is-yayasan="$isYayasan ?? false" :active-lembaga="$activeLembaga ?? null" />
                </div>
                <p class="text-xs text-gray-500 sm:text-sm">
                    Kelola jadwal piket mingguan guru, pemantauan kalender piket harian, dan penugasan override khusus.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                @if (! ($isYayasanAggregate ?? false))
                    <x-link-button href="{{ route('admin.piket-guru.create') }}" class="shadow-sm">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah Jadwal
                    </x-link-button>
                @else
                    <x-tooltip text="Pilih 1 lembaga aktif melalui pengalih lembaga di atas untuk menambah jadwal piket.">
                        <x-secondary-button type="button" disabled class="cursor-not-allowed opacity-60 shadow-sm">
                            <span class="text-base leading-none mr-1.5">+</span> Tambah Jadwal
                        </x-secondary-button>
                    </x-tooltip>
                @endif
            </div>
        </div>

        {{-- 4 Stat Tiles KPI Summary --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-tile tone="blue" label="Total Jadwal" :value="$stats['totalJadwal'] ?? 0" icon="calendar_month" hint="Jadwal mingguan aktif" />
            <x-stat-tile tone="green" label="Guru Bertugas" :value="$stats['guruTerjadwal'] ?? 0" icon="school" hint="Guru piket terdaftar" />
            <x-stat-tile tone="amber" label="Override Aktif" :value="$stats['overrideAktif'] ?? 0" icon="edit_calendar" hint="Penugasan khusus mendatang" />
            <x-stat-tile
                tone="indigo"
                :label="($isYayasanAggregate ?? false) ? 'Unit Terjadwal' : 'Hari Tercover'"
                :value="$stats['lembagaTerjadwal'] ?? 0"
                :icon="($isYayasanAggregate ?? false) ? 'apartment' : 'date_range'"
                :hint="($isYayasanAggregate ?? false) ? 'Lembaga memiliki jadwal' : 'Hari piket terisi'"
            />
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <x-icon name="filter_list" class="h-4 w-4 text-gray-500" />
                    <h3 class="font-display text-sm font-bold text-gray-900">Filter &amp; Pencarian Data</h3>
                </div>
                @if ($hasActiveFilter)
                    <a
                        href="{{ route('admin.piket-guru.index') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800 transition"
                    >
                        <x-icon name="close" class="h-3.5 w-3.5" />
                        Reset Filter
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.piket-guru.index') }}" class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 items-end">
                {{-- Search Guru --}}
                <div>
                    <x-input-label value="Cari Guru" class="text-xs mb-1.5" />
                    <div class="relative">
                        <x-text-input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Ketik nama guru..."
                            class="w-full text-xs sm:text-sm pl-8"
                        />
                        <x-icon name="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" />
                    </div>
                </div>

                {{-- Filter Hari --}}
                <div>
                    <x-input-label value="Hari Piket" class="text-xs mb-1.5" />
                    <x-select name="hari" class="w-full text-xs sm:text-sm">
                        <option value="">Semua Hari</option>
                        @foreach ($namaHari as $idx => $labelHari)
                            <option value="{{ $idx }}" @selected(($filters['hari'] !== null && $filters['hari'] !== '') && (int) $filters['hari'] === $idx)>
                                {{ $labelHari }}
                            </option>
                        @endforeach
                    </x-select>
                </div>

                {{-- Filter Semester --}}
                <div>
                    <x-input-label value="Semester" class="text-xs mb-1.5" />
                    <x-select name="semester_id" class="w-full text-xs sm:text-sm">
                        <option value="">Semua Semester</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected(($filters['semester_id'] ?? null) == $sem->id)>
                                {{ $sem->tahunAjaran->nama ?? '' }} - {{ $sem->nama }}{{ ($isYayasanAggregate && $sem->lembaga) ? ' ('.$sem->lembaga->nama.')' : '' }}
                            </option>
                        @endforeach
                    </x-select>
                </div>

                {{-- Filter Lembaga (Hanya saat agregat) --}}
                @if ($isYayasanAggregate ?? false)
                    <div>
                        <x-input-label value="Unit Lembaga" class="text-xs mb-1.5" />
                        <x-select name="lembaga_id" class="w-full text-xs sm:text-sm">
                            <option value="">Semua Lembaga</option>
                            @foreach ($lembagaList as $lem)
                                <option value="{{ $lem->id }}" @selected(($filters['lembaga_id'] ?? null) == $lem->id)>
                                    {{ $lem->nama }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>
                @endif

                {{-- Tombol Submit Filter --}}
                <div class="flex items-center gap-2 {{ ($isYayasanAggregate ?? false) ? 'sm:col-span-2 lg:col-span-4 justify-end' : '' }}">
                    <x-primary-button type="submit" class="w-full sm:w-auto justify-center">
                        <x-icon name="search" class="h-3.5 w-3.5 mr-1" />
                        Terapkan Filter
                    </x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabbed Navigation Bar --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-4 sm:space-x-8" aria-label="Tabs">
                <button
                    type="button"
                    @click="activeTab = 'mingguan'"
                    :class="activeTab === 'mingguan' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="flex items-center gap-2 border-b-2 py-3 px-1 text-xs sm:text-sm transition whitespace-nowrap"
                >
                    <x-icon name="calendar_month" class="h-4 w-4" />
                    Jadwal Mingguan
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-mono transition"
                        :class="activeTab === 'mingguan' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ $jadwalList->count() }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'kalender'"
                    :class="activeTab === 'kalender' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="flex items-center gap-2 border-b-2 py-3 px-1 text-xs sm:text-sm transition whitespace-nowrap"
                >
                    <x-icon name="event" class="h-4 w-4" />
                    Kalender Piket Harian
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-mono transition"
                        :class="activeTab === 'kalender' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ $piketHarianMendatang->count() }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'override'"
                    :class="activeTab === 'override' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="flex items-center gap-2 border-b-2 py-3 px-1 text-xs sm:text-sm transition whitespace-nowrap"
                >
                    <x-icon name="edit_calendar" class="h-4 w-4" />
                    Override Manual
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-mono transition"
                        :class="activeTab === 'override' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ $overrides->count() }}
                    </span>
                </button>
            </nav>
        </div>

        {{-- TAB 1: Jadwal Piket Mingguan --}}
        <div x-show="activeTab === 'mingguan'" class="space-y-4" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <th class="px-5 py-3.5">Guru Piket</th>
                            <th class="px-5 py-3.5">Hari Bertugas</th>
                            <th class="px-5 py-3.5">Semester</th>
                            @if ($isYayasanAggregate ?? false)
                                <th class="px-5 py-3.5">Lembaga</th>
                            @endif
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($jadwalList as $jadwal)
                            <tr class="hover:bg-gray-50/75 transition">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">
                                            {{ strtoupper(substr($jadwal->guru?->nama ?? 'G', 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $jadwal->guru?->nama ?? '-' }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ $jadwal->guru?->nip ? 'NIP: '.$jadwal->guru->nip : ($jadwal->guru?->nuptk ? 'NUPTK: '.$jadwal->guru->nuptk : 'Guru') }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badgeHariTone[$jadwal->hari] ?? 'bg-gray-50 text-gray-700 border-gray-200' }}">
                                        {{ $namaHari[$jadwal->hari] ?? $jadwal->hari }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600 text-xs">
                                    {{ $jadwal->semester ? $jadwal->semester->tahunAjaran->nama . ' - ' . $jadwal->semester->nama : '-' }}
                                </td>
                                @if ($isYayasanAggregate ?? false)
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 border border-purple-200">
                                            <x-icon name="apartment" class="h-3 w-3" />
                                            {{ $jadwal->lembaga?->nama ?? '-' }}
                                        </span>
                                    </td>
                                @endif
                                <td class="px-5 py-3.5 text-right space-x-3 text-xs font-medium">
                                    <a href="{{ route('admin.piket-guru.edit', $jadwal) }}" class="text-brand-600 hover:text-brand-800 transition hover:underline">Edit</a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.piket-guru.destroy', $jadwal) }}"
                                        class="inline"
                                        @submit.prevent="confirmDialog('Hapus Jadwal Piket?', @js('Piket ' . ($jadwal->guru?->nama ?? 'guru ini') . ' pada hari ' . ($namaHari[$jadwal->hari] ?? $jadwal->hari) . ' akan dihapus. Baris piket harian otomatis yang terkait akan disinkronkan kembali.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-error-600 hover:text-error-800 transition hover:underline ml-2">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isYayasanAggregate ?? false) ? 5 : 4 }}" class="px-5 py-12 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center justify-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 mb-3">
                                            <x-icon name="calendar_month" class="h-6 w-6" />
                                        </div>
                                        <p class="font-display text-sm font-semibold text-gray-900">Belum ada jadwal piket mingguan</p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ $hasActiveFilter ? 'Tidak ada data jadwal piket yang cocok dengan filter yang dipilih.' : 'Atur jadwal piket mingguan guru agar sistem dapat menjadwalkan piket harian secara otomatis.' }}
                                        </p>
                                        @if (! ($isYayasanAggregate ?? false) && ! $hasActiveFilter)
                                            <x-link-button href="{{ route('admin.piket-guru.create') }}" class="mt-4 shadow-sm text-xs">
                                                Tambah Jadwal Sekarang
                                            </x-link-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 2: Kalender Piket Harian Mendatang --}}
        <div x-show="activeTab === 'kalender'" style="display: none;" class="space-y-4" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 text-xs text-blue-800 flex items-start gap-3">
                <x-icon name="info" class="h-4 w-4 shrink-0 text-blue-600 mt-0.5" />
                <div class="space-y-0.5">
                    <p class="font-semibold">Sinkronisasi Kalender Piket Harian</p>
                    <p class="text-blue-700">Dihasilkan otomatis dari Jadwal Piket Mingguan dan digabung dengan Override Manual. Menampilkan maksimal 60 penugasan mendatang. Baris "Otomatis" diperbarui melalui Jadwal Mingguan.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <th class="px-5 py-3.5">Tanggal</th>
                            <th class="px-5 py-3.5">Guru Piket Bertugas</th>
                            <th class="px-5 py-3.5">Sumber Penugasan</th>
                            @if ($isYayasanAggregate ?? false)
                                <th class="px-5 py-3.5">Lembaga</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($piketHarianMendatang as $item)
                            <tr class="hover:bg-gray-50/75 transition">
                                <td class="px-5 py-3.5 font-medium text-gray-900 text-xs sm:text-sm">
                                    {{ \Carbon\Carbon::parse($item->tanggal)->isoFormat('dddd, D MMMM Y') }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[11px] font-bold text-gray-600">
                                            {{ strtoupper(substr($item->guru?->nama ?? 'G', 0, 2)) }}
                                        </div>
                                        <span class="font-medium text-gray-800 text-xs sm:text-sm">{{ $item->guru?->nama ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($item->sumber === 'override_manual')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 border border-purple-200 px-2.5 py-0.5 text-[11px] font-semibold text-purple-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                                            Override Manual
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                            Otomatis
                                        </span>
                                    @endif
                                </td>
                                @if ($isYayasanAggregate ?? false)
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 border border-purple-200">
                                            <x-icon name="apartment" class="h-3 w-3" />
                                            {{ $item->lembaga?->nama ?? '-' }}
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isYayasanAggregate ?? false) ? 4 : 3 }}" class="px-5 py-12 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center justify-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 mb-3">
                                            <x-icon name="event" class="h-6 w-6" />
                                        </div>
                                        <p class="font-display text-sm font-semibold text-gray-900">Belum ada kalender piket harian</p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            Baris piket harian akan otomatis dibuat saat Jadwal Piket Mingguan ditambahkan.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 3: Override Manual Piket Harian --}}
        <div x-show="activeTab === 'override'" style="display: none;" class="space-y-6" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            {{-- Form Tambah Override (Aktif saat 1 lembaga dipilih) --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="font-display text-base font-bold text-gray-900">Penugasan Override Manual</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Tugaskan guru piket pengganti atau penugasan khusus pada tanggal spesifik. Override bersifat permanen dan tidak tertimpa sinkronisasi otomatis.</p>
                </div>

                @if (! ($isYayasanAggregate ?? false) || ($activeLembaga ?? null))
                    <form method="POST" action="{{ route('admin.piket-harian.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                        @csrf
                        <div class="relative z-20" x-data="tomSelectPegawai({
                            options: @js($guruOptions),
                            oldValue: @js(old('guru_id', '')),
                            placeholder: '— Pilih atau cari guru —'
                        })">
                            <x-input-label value="Pilih Guru Piket" class="text-xs mb-1.5" />
                            <div>
                                <select
                                    name="guru_id"
                                    x-ref="selectElement"
                                    class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                                    autocomplete="off"
                                    required
                                >
                                    <option value="">— Pilih atau cari guru —</option>
                                    @foreach ($guruList ?? [] as $guru)
                                        <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Tanggal Piket" class="text-xs mb-1.5" />
                            <x-text-input type="date" name="tanggal" required class="w-full text-sm" value="{{ now()->toDateString() }}" />
                        </div>

                        <div>
                            <x-primary-button type="submit" class="w-full justify-center">
                                <x-icon name="add" class="h-4 w-4 mr-1" />
                                Simpan Override
                            </x-primary-button>
                        </div>
                    </form>
                @else
                    <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                        <x-icon name="warning" class="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                        <div>
                            <p class="font-semibold">Pilih 1 Lembaga untuk Menambah Override</p>
                            <p class="mt-0.5 text-amber-700">Penugasan override manual harus terikat ke satu lembaga spesifik. Silakan gunakan pengalih lembaga di topbar untuk memilih unit sekolah yang ingin ditugaskan.</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Tabel Daftar Override Aktif Mendatang --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card space-y-0">
                <div class="border-b border-gray-100 px-5 py-3.5 bg-gray-50 flex items-center justify-between">
                    <h3 class="font-display text-xs font-bold uppercase tracking-wider text-gray-500">Daftar Override Mendatang</h3>
                    <span class="text-xs text-gray-500">{{ $overrides->count() }} data aktif</span>
                </div>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 text-xs font-semibold text-gray-500 border-b border-gray-100">
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Guru Piket</th>
                            @if ($isYayasanAggregate ?? false)
                                <th class="px-5 py-3">Lembaga</th>
                            @endif
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($overrides as $override)
                            <tr class="hover:bg-gray-50/75 transition">
                                <td class="px-5 py-3 font-medium text-gray-900 text-xs sm:text-sm">
                                    {{ \Carbon\Carbon::parse($override->tanggal)->isoFormat('dddd, D MMMM Y') }}
                                </td>
                                <td class="px-5 py-3 text-gray-700 text-xs sm:text-sm">
                                    {{ $override->guru?->nama ?? '-' }}
                                </td>
                                @if ($isYayasanAggregate ?? false)
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 border border-purple-200">
                                            <x-icon name="apartment" class="h-3 w-3" />
                                            {{ $override->lembaga?->nama ?? '-' }}
                                        </span>
                                    </td>
                                @endif
                                <td class="px-5 py-3 text-right">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.piket-harian.destroy', $override) }}"
                                        class="inline"
                                        @submit.prevent="confirmDialog('Hapus Override Piket?', @js('Override piket manual untuk ' . \Carbon\Carbon::parse($override->tanggal)->isoFormat('D MMMM Y') . ' akan dihapus.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-error-600 hover:text-error-800 transition hover:underline">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isYayasanAggregate ?? false) ? 4 : 3 }}" class="px-5 py-8 text-center text-xs text-gray-500">
                                    Tidak ada override manual aktif mendatang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
