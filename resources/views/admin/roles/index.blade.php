<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js($search),
            scope: @js($scope)
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.roles.index')),
    })">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Role Builder</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola tingkat kewenangan dan akses izin per modul secara spesifik.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Akses & Peran</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="shield" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Roles</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalRoles }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="domain" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Scope Yayasan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalYayasan }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Scope Lembaga</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalLembaga }}</p>
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
                <x-tooltip text="Tambah tingkat role kewenangan baru">
                    <x-link-button href="{{ route('admin.roles.create') }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] sm:w-auto">
                        <span class="text-base leading-none">+</span> Tambah Role
                    </x-link-button>
                </x-tooltip>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Role</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama role..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Scope Level</label>
                    <select x-model="filters.scope" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Scope</option>
                        <option value="yayasan">Yayasan</option>
                        <option value="lembaga">Lembaga</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="relative rounded-2xl border border-gray-200 bg-white shadow-card">
            <div id="tabel-container" x-ref="tableContainer">
                @include('admin.roles._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
