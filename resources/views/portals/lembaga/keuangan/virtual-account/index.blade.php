<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700 shadow-sm" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Virtual Account</h1>
                <p class="mt-0.5 text-xs text-gray-500">Kelola nomor Virtual Account BRI siswa untuk top-up wallet.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Virtual Account</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="virtualAccountFilter({
                search: @js(request('search', '')),
                kelasId: @js(request('kelas_id', '')),
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.virtual-account.index')),
            })"
        >
            {{-- KPI Compact Horizontal Statistic Cards --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <x-icon name="payments" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Siswa Ber-VA</p>
                            <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($totalVa ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-400">Aktif</span>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <x-icon name="check_circle" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Total Saldo Terkumpul</p>
                            <p class="font-display text-lg font-bold text-gray-900 leading-tight font-mono">Rp{{ number_format($totalSaldo ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-400">Wallet</span>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <x-icon name="person_add" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Belum Ada VA</p>
                            <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($totalBelumVa ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-medium text-gray-400">Calon</span>
                </div>
            </div>

            {{-- Filter & Action Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.virtual-account.export') }}" title="Ekspor daftar Virtual Account ke file Excel" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            <x-icon name="description" class="h-4 w-4 text-emerald-600" />
                            Export Excel
                        </a>
                        <button type="button" @click="$dispatch('open-generate-va-modal')" title="Buat nomor Virtual Account baru untuk siswa" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                            <x-icon name="add" class="h-4 w-4" />
                            Generate VA
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:items-end">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Nama/NIS Siswa</label>
                        <div class="flex h-[42px] items-center gap-2 rounded-[10px] border border-gray-200 bg-gray-50 px-3.5">
                            <x-icon name="search" class="h-[14px] w-[14px] shrink-0 text-gray-400" />
                            <input type="text" x-model="search" @input.debounce.400ms="muatUlangDaftar()" placeholder="Nama atau NIS siswa..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                        <select x-model="kelasId" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Kelas</option>
                            @foreach ($kelasListGrouped ?? [] as $tahunAjaranNama => $grupKelas)
                                <optgroup label="{{ $tahunAjaranNama }}">
                                    @foreach ($grupKelas as $kelas)
                                        <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="daftarVirtualAccount">
                @include('portals.lembaga.keuangan.virtual-account._daftar')
            </div>

            @include('portals.lembaga.keuangan.virtual-account._riwayat-modal')
            @include('portals.lembaga.keuangan.virtual-account._topup-modal')
            @include('portals.lembaga.keuangan.virtual-account._generate-modal')
        </div>
    </div>
</x-app-layout>
