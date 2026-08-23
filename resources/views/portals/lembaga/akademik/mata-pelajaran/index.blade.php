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
                <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
                <p class="mt-0.5 text-xs text-gray-500">Kelola daftar mata pelajaran dan aspek perkembangan untuk kurikulum lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Mata Pelajaran</b>
            </p>
        </div>

        {{-- KPI Compact Horizontal Statistic Cards (Samakan dengan Jabatan Tambahan) --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="menu_book" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Mapel</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalMapel ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Semua Data</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Kurikulum Mapel</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $countKurikulum ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">SD - SMK</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="extension" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Aspek Perkembangan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $countAspek ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">PAUD / TK</span>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="mataPelajaranFilter({
                search: @js(request('search', '')),
                tipe: @js(request('tipe', '')),
                kelompok: @js(request('kelompok', '')),
                status: @js(request('status', '')),
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.mata-pelajaran.index')),
            })"
        >
            {{-- Filter Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter &amp; Aksi Data
                    </p>
                    @can('mata-pelajaran.create')
                    <x-link-button href="{{ route('admin.mata-pelajaran.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Mata Pelajaran
                    </x-link-button>
                    @endcan
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    {{-- Search --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Kata Kunci</label>
                        <div class="flex h-[42px] items-center gap-2 rounded-[10px] border border-gray-200 bg-gray-50 px-3.5">
                            <x-icon name="search" class="h-[14px] w-[14px] shrink-0 text-gray-400" />
                            <input
                                type="text"
                                x-model="search"
                                @input.debounce.400ms="muatUlangDaftar()"
                                placeholder="Nama atau Kode mapel..."
                                class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>

                    {{-- Filter Tipe --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tipe Kurikulum</label>
                        <select x-ref="tipeSelect" x-init="initFilterSelect($refs.tipeSelect, 'tipe')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Tipe</option>
                            @foreach ($tipeList as $item)
                                <option value="{{ $item->value }}" @selected(request('tipe') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Kelompok --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelompok Mapel</label>
                        <select x-ref="kelompokSelect" x-init="initFilterSelect($refs.kelompokSelect, 'kelompok')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Kelompok</option>
                            @foreach ($kelompokList as $item)
                                <option value="{{ $item->value }}" @selected(request('kelompok') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Status --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status Keaktifan</label>
                        <select x-ref="statusSelect" x-init="initFilterSelect($refs.statusSelect, 'status')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Status</option>
                            @foreach ($statusList as $item)
                                <option value="{{ $item->value }}" @selected(request('status') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Table Wrapper --}}
            <div x-ref="daftarMataPelajaran">
                @include('portals.lembaga.akademik.mata-pelajaran._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
