<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Lembaga</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Lembaga</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="corporate_fare" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Lembaga</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalLembaga ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Semua Data</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="account_balance" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Lembaga Negeri</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalNegeri ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="domain" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Lembaga Swasta</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalSwasta ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    cari: @js(request('cari', '')),
                    bentuk: @js(request('bentuk', '')),
                    status: @js(request('status', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.lembaga.index'))
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    @if (auth()->user()->widestScopeLevel() === 'yayasan')
                        <x-link-button href="{{ route('admin.lembaga.create') }}">
                            <span class="text-base leading-none">+</span> Tambah Lembaga
                        </x-link-button>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="cari" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                            <input
                                type="text" x-model="filters.cari" @input.debounce.500ms="muatUlangDaftar()"
                                placeholder="Nama atau NPSN"
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Bentuk Pendidikan</label>
                        <select x-ref="bentukSelect" x-init="initFilterSelect($refs.bentukSelect, 'bentuk', false)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Bentuk</option>
                            @foreach (['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'] as $bentuk)
                                <option value="{{ $bentuk }}" @selected(request('bentuk') === $bentuk)>{{ $bentuk }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status Sekolah</label>
                        <select x-ref="statusSelect" x-init="initFilterSelect($refs.statusSelect, 'status', false)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Status</option>
                            <option value="negeri" @selected(request('status') === 'negeri')>Negeri</option>
                            <option value="swasta" @selected(request('status') === 'swasta')>Swasta</option>
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="tableContainer">
                @include('admin.lembaga._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
