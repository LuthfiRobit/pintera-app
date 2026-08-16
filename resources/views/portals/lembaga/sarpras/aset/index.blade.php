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
                <h1 class="font-display text-lg font-bold text-gray-900">Aset & Inventaris Barang</h1>
                <p class="text-xs text-gray-500 mt-0.5">Pencatatan inventaris sarana prasarana sekolah (Metode Barcode Unik & Kuantitas Ruangan).</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Aset</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="inventory_2" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Unit Barang</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($totalItem ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Unit</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="payments" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Total Nilai Aset</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight">Rp {{ number_format($totalNilai ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Kondisi Baik</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($totalBaik ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Layak</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <x-icon name="build" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-rose-600">Perlu Perbaikan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($totalRusak ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Rusak</span>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    search: @js(request('search', '')),
                    kategori_id: @js(request('kategori_id', '')),
                    ruangan_id: @js(request('ruangan_id', '')),
                    kondisi: @js(request('kondisi', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.sarpras.aset.index'))
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <div class="flex items-center gap-2">
                        <x-link-button variant="secondary" href="{{ route('admin.sarpras.mutasi.index') }}">
                            <x-icon name="swap_horiz" class="h-4 w-4" /> Riwayat Mutasi
                        </x-link-button>
                        <x-link-button href="{{ route('admin.sarpras.aset.create') }}">
                            <span class="text-base leading-none">+</span> Tambah Aset
                        </x-link-button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                            <input
                                type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                                placeholder="Nama, kode, merk..."
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>

                    {{-- Filter Kategori --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kategori</label>
                        <select x-model="filters.kategori_id" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Kategori</option>
                            @foreach ($kategoriOptions as $k)
                                <option value="{{ $k->id }}">{{ $k->nama_kategori }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Ruangan --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Ruangan</label>
                        <select x-model="filters.ruangan_id" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Ruangan</option>
                            @foreach ($ruanganOptions as $r)
                                <option value="{{ $r->id }}">{{ $r->nama_ruangan }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Kondisi --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kondisi</label>
                        <select x-model="filters.kondisi" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Kondisi</option>
                            @foreach ($kondisiOptions as $kondisi)
                                <option value="{{ $kondisi->value }}">{{ $kondisi->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="tableContainer">
                @include('portals.lembaga.sarpras.aset._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
