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
                <h1 class="font-display text-lg font-bold text-gray-900">Kategori Aset</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola pengelompokan jenis barang sarana prasarana dan inventaris sekolah.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kategori Aset</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="tags" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Kategori</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalKategori ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Klasifikasi</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="inventory_2" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-blue-600">Total Aset Terkategori</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalAset ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Barang</span>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    search: @js(request('search', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.sarpras.kategori.index'))
            })"
        >
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {{-- Form Tambah Kategori --}}
                <div class="lg:col-span-1">
                    <form action="{{ route('admin.sarpras.kategori.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                        @csrf
                        <p class="font-display text-sm font-bold text-gray-900">Tambah Kategori Baru</p>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Kategori <span class="text-rose-500">*</span></label>
                            <input type="text" name="kode_kategori" value="{{ old('kode_kategori') }}" required placeholder="Contoh: ELK, MEB, KBM" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <x-input-error :messages="$errors->get('kode_kategori')" class="mt-1" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Kategori <span class="text-rose-500">*</span></label>
                            <input type="text" name="nama_kategori" value="{{ old('nama_kategori') }}" required placeholder="Contoh: Elektronik & IT" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <x-input-error :messages="$errors->get('nama_kategori')" class="mt-1" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Deskripsi</label>
                            <textarea name="deskripsi" rows="2" placeholder="Keterangan kategori..." class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi') }}</textarea>
                            <x-input-error :messages="$errors->get('deskripsi')" class="mt-1" />
                        </div>

                        <x-primary-button type="submit" class="w-full justify-center">
                            + Tambah Kategori
                        </x-primary-button>
                    </form>
                </div>

                {{-- Table Kategori Container --}}
                <div class="lg:col-span-2 space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                                Filter Data
                            </p>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                                <input
                                    type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                                    placeholder="Cari kode atau nama kategori..."
                                    class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                                >
                            </div>
                        </div>
                    </div>

                    <div x-ref="tableContainer">
                        @include('portals.lembaga.sarpras.kategori._daftar')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
