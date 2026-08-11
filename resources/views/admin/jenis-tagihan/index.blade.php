<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700 shadow-sm" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Jenis Tagihan</h1>
                <p class="mt-0.5 text-xs text-gray-500">Kelola daftar komponen biaya dan master tagihan lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jenis Tagihan</b>
            </p>
        </div>

        <div
            x-data="{
                ...jenisTagihanTable({
                    initialItems: @js($jenisTagihanList),
                    deleteUrlTemplate: @js(route('admin.jenis-tagihan.destroy', ['jenisTagihan' => '__ID__'])),
                    nominalUrlTemplate: @js(route('admin.jenis-tagihan.nominal', ['jenisTagihan' => '__ID__'])),
                    editUrlTemplate: @js(route('admin.jenis-tagihan.edit', ['jenisTagihan' => '__ID__'])),
                    prosesUrlTemplate: @js(route('admin.jenis-tagihan.proses', ['jenisTagihan' => '__ID__'])),
                    monitoringUrlTemplate: @js(route('admin.jenis-tagihan.monitoring.index', ['jenisTagihan' => '__ID__'])),
                }),
                search: '',
                get filteredItems() {
                    if (this.search === '') return this.items;
                    const q = this.search.toLowerCase();
                    return this.items.filter(i => i.nama.toLowerCase().includes(q) || i.kategori.toLowerCase().includes(q));
                },
                get totalAktif() {
                    return this.items.filter(i => i.is_active).length;
                },
                get totalDipakai() {
                    return this.items.filter(i => i.tagihan_item_count > 0).length;
                }
            }"
            class="space-y-4"
        >
            {{-- KPI Compact Horizontal Statistic Cards --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <x-icon name="receipt_long" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Jenis</p>
                            <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-400">Semua Data</span>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <x-icon name="check_circle" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Status Aktif</p>
                            <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="totalAktif"></p>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-400">Siap Diproses</span>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <x-icon name="payments" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-brand-600">Terpakai</p>
                            <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="totalDipakai"></p>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-400">Didaftarkan</span>
                </div>
            </div>

            {{-- Filter Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter &amp; Aksi Data
                    </p>
                    @can('jenis-tagihan.create')
                    <x-link-button href="{{ route('admin.jenis-tagihan.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Jenis Tagihan
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
                                placeholder="Nama tagihan..."
                                class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table Wrapper --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div id="table-container">
                    @include('admin.jenis-tagihan._daftar')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
