<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
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
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.virtual-account.export') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Export Excel</a>
                        <button type="button" @click="$dispatch('open-generate-va-modal')" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700">Generate VA</button>
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
                        <select x-model="kelasId" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 text-sm">
                            <option value="">Semua Kelas</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="daftarVirtualAccount">
                @include('admin.virtual-account._daftar')
            </div>

            @include('admin.virtual-account._riwayat-modal')
            @include('admin.virtual-account._generate-modal')
        </div>
    </div>
</x-app-layout>
