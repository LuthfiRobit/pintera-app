<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Ruangan & Fasilitas</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola direktori ruang kelas teori, laboratorium, kantor, dan fasilitas bersama.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Ruangan</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="meeting_room" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Ruang</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalRuangan ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Unit</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Kelas Teori</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalKelas ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Rombel</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="science" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Laboratorium</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalLab ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Praktik</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                        <x-icon name="share" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-purple-600">Shared Facility</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalShared ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Bersama</span>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    search: @js(request('search', '')),
                    gedung_id: @js(request('gedung_id', '')),
                    jenis_ruangan: @js(request('jenis_ruangan', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.sarpras.ruangan.index'))
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <x-link-button href="{{ route('admin.sarpras.ruangan.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Ruangan
                    </x-link-button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {{-- Search --}}
                    <div>
                        <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                            <input
                                type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                                placeholder="Kode atau nama ruangan..."
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>

                    {{-- Filter Gedung --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Gedung</label>
                        <select x-model="filters.gedung_id" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Gedung</option>
                            @foreach ($gedungOptions as $g)
                                <option value="{{ $g->id }}">{{ $g->nama_gedung }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Jenis Ruangan --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Jenis Ruangan</label>
                        <select x-model="filters.jenis_ruangan" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Jenis</option>
                            @foreach ($jenisOptions as $jenis)
                                <option value="{{ $jenis->value }}">{{ $jenis->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="tableContainer">
                @include('portals.lembaga.sarpras.ruangan._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
