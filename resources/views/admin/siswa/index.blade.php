<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js(request('search')),
            kelas_id: @js(request('kelas_id')),
            status: @js(request('status')),
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.siswa.index')),
    })">
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
                <h1 class="font-display text-lg font-bold text-gray-900">Siswa</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data induk siswa, penempatan rombel kelas, serta pemantauan status aktif siswa.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Siswa</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-blue-600">Total Siswa</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalSiswa }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Semua Status</span>
            </div>
            
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Siswa Aktif</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $totalAktif }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Terdaftar</span>
            </div>
            
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="warning" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Tanpa Akun (Aktif)</p>
                        <p class="font-display text-lg font-bold leading-tight text-gray-900">{{ $siswaTanpaAkunCount }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Perlu Tindakan</span>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <p class="flex shrink-0 items-center gap-2 text-sm font-semibold text-gray-700 whitespace-nowrap">
                    <x-icon name="filter_alt" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    @if (($siswaTanpaAkunCount ?? 0) > 0 && auth()->user()->can('siswa.edit'))
                        <x-tooltip text="Terdapat {{ $siswaTanpaAkunCount }} siswa aktif tanpa akun login. Klik untuk membangkitkan username login baru dan password default (berdasarkan NIS) secara massal.">
                            <form
                                method="POST"
                                action="{{ route('admin.siswa.generate-akun-massal') }}"
                                x-data
                                @submit.prevent="confirmDialog('Generate Akun Massal?', 'Bangkitkan akun login baru secara massal untuk {{ $siswaTanpaAkunCount }} siswa aktif yang belum memiliki akun?', { confirmLabel: 'Ya, Generate Massal' }).then(confirmed => { if (confirmed) $el.submit() })"
                                class="inline-block"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition active:scale-[0.98] hover:bg-gray-50"
                                >
                                    <x-icon name="person_add" class="h-4 w-4" />
                                    <span>Generate Akun Massal ({{ $siswaTanpaAkunCount }})</span>
                                </button>
                            </form>
                        </x-tooltip>
                    @endif
                    @can('siswa.spmb-daftar')
                        <x-link-button variant="ghost" href="{{ route('admin.siswa.spmb-daftar.index') }}">
                            <x-icon name="check_circle" class="h-4 w-4" /> Daftarkan dari SPMB
                        </x-link-button>
                    @endcan
                    @can('siswa.import')
                        <x-link-button variant="ghost" href="{{ route('admin.siswa.import.index') }}">
                            <x-icon name="upload" class="h-4 w-4" /> Import Siswa
                        </x-link-button>
                    @endcan
                    <x-link-button href="{{ route('admin.siswa.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Siswa
                    </x-link-button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4 lg:items-end">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Siswa</label>
                    <div class="flex h-[42px] items-center gap-2 rounded-[10px] border border-gray-200 bg-gray-50 px-3.5">
                        <x-icon name="search" class="h-[14px] w-[14px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama atau NIS..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Filter Kelas</label>
                    <select x-ref="kelasSelect" x-init="initFilterSelect($refs.kelasSelect, 'kelas_id', true)" class="w-full rounded-[10px] border-gray-200 text-sm text-gray-700">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(request('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Filter Status</label>
                    <select x-ref="statusSelect" x-init="initFilterSelect($refs.statusSelect, 'status')" class="w-full rounded-[10px] border-gray-200 text-sm text-gray-700">
                        <option value="">Semua Status</option>
                        @foreach ($statusList as $s)
                            <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Container Tabel Data --}}
        <div class="relative rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Loading Overlay --}}
            <div x-show="false" style="display: none;" class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/60 backdrop-blur-sm"
                 x-transition.opacity
                 @ajax-start.window="$el.style.display = 'flex'"
                 @ajax-end.window="$el.style.display = 'none'">
                <x-icon name="sync" class="h-8 w-8 animate-spin text-brand-500" />
            </div>

            <div id="tabel-container" x-ref="tableContainer">
                @include('admin.siswa._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
