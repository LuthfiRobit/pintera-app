<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js($search),
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.users.index')),
    })">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Manajemen Akun Staff</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola akun akses sistem untuk pengguna staf, guru, dan pengurus.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Akses & Peran</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Akun</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalUsers }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Akun Aktif</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalAktif }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500">
                        <x-icon name="block" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Nonaktif</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalNonaktif }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter_alt" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                @can('users.create')
                <x-tooltip text="Tambah akun staff baru">
                    <x-link-button href="{{ route('admin.users.create') }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] sm:w-auto">
                        <span class="text-base leading-none">+</span> Tambah Akun
                    </x-link-button>
                </x-tooltip>
                @endcan
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Akun</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama atau email..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
            </div>
        </div>

        <div class="relative rounded-2xl border border-gray-200 bg-white shadow-card">
            <div x-show="loading" class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/60 backdrop-blur-sm" style="display: none;">
                <div class="flex items-center gap-3 rounded-full bg-white px-4 py-2 shadow-elevated ring-1 ring-gray-900/5">
                    <svg class="h-4 w-4 animate-spin text-brand-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-xs font-medium text-gray-700">Memuat data...</span>
                </div>
            </div>

            <div id="tabel-container">
                @include('admin.users._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
