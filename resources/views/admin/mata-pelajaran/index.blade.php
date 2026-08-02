<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
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

        {{-- KPI Compact Horizontal Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-stat-tile label="Total Mata Pelajaran" value="{{ $totalMapel ?? 0 }}" icon="menu_book" color="blue" class="p-4" />
            <x-stat-tile label="Kurikulum Mapel (SD-SMK)" value="{{ $countKurikulum ?? 0 }}" icon="school" color="green" class="p-4" />
            <x-stat-tile label="Aspek Perkembangan (PAUD/TK)" value="{{ $countAspek ?? 0 }}" icon="extension" color="amber" class="p-4" />
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
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <x-link-button href="{{ route('admin.mata-pelajaran.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Mata Pelajaran
                    </x-link-button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Cari Kata Kunci</label>
                        <div class="relative">
                            <x-icon name="search" class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                x-model="search"
                                @input.debounce.400ms="muatUlangDaftar()"
                                placeholder="Nama atau Kode mapel..."
                                class="w-full rounded-lg border-gray-200 py-1.5 pl-8 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500"
                            >
                        </div>
                    </div>

                    {{-- Filter Tipe --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tipe Kurikulum</label>
                        <select x-ref="tipeSelect" x-init="initFilterSelect($refs.tipeSelect, 'tipe')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Tipe</option>
                            @foreach ($tipeList as $item)
                                <option value="{{ $item->value }}" @selected(request('tipe') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Kelompok --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kelompok Mapel</label>
                        <select x-ref="kelompokSelect" x-init="initFilterSelect($refs.kelompokSelect, 'kelompok')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Kelompok</option>
                            @foreach ($kelompokList as $item)
                                <option value="{{ $item->value }}" @selected(request('kelompok') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Status --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Status Keaktifan</label>
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
                @include('admin.mata-pelajaran._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
