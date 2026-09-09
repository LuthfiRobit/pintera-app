<x-app-layout>
    <div
        class="mx-auto max-w-6xl space-y-5"
        x-data="komponenPenilaianFilter({
            tahunAjaranId: @js($tahunAjaranId),
            semesterId: @js($semesterId),
            mataPelajaranId: @js($mataPelajaranId),
            search: @js($search),
            opsiUrl: @js(route('admin.komponen-penilaian.opsi')),
            indexUrl: @js(route('admin.komponen-penilaian.index')),
        })"
    >
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold text-gray-900">Komponen Penilaian (TP)</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Kelola Tujuan Pembelajaran (TP), indikator ketercapaian (KKTP), dan pembobotan nilai per mata pelajaran.
                </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                    <x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="shadow-sm">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
                    </x-link-button>
                @else
                    <x-tooltip text="Pilih 1 lembaga aktif dulu untuk menambah TP.">
                        <x-secondary-button type="button" disabled class="cursor-not-allowed">
                            <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
                        </x-secondary-button>
                    </x-tooltip>
                @endif
            </div>
        </div>

        {{-- FILTER & CONTROLS TOOLBAR (Searchable TomSelect Standar Pintera) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            {{-- Header of Filter --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3.5">
                <div>
                    <h3 class="font-display text-sm font-bold text-gray-900">Filter &amp; Pencarian TP</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Filter otomatis diperbarui secara instan tanpa reload halaman.</p>
                </div>
                <button
                    type="button"
                    x-show="tahunAjaranId || semesterId || mataPelajaranId || search"
                    @click="resetFilters()"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-800 transition self-start sm:self-auto"
                >
                    <span>✕ Reset Semua Filter</span>
                </button>
            </div>

            {{-- 3 Searchable TomSelect Filters --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                {{-- 1. Tahun Ajaran (Searchable TomSelect) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Tahun Ajaran</label>
                    <select
                        x-ref="tahunAjaranSelect"
                        x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
                                {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 2. Semester (Searchable TomSelect) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Semester</label>
                    <select
                        x-ref="semesterSelect"
                        x-init="initSemesterSelect($refs.semesterSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Semester</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($semesterId == $sem->id)>
                                {{ $sem->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 3. Mata Pelajaran (Searchable TomSelect) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-600">Mata Pelajaran</label>
                    <select
                        x-ref="mataPelajaranSelect"
                        x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm"
                    >
                        <option value="">Semua Mata Pelajaran</option>
                        @foreach ($mataPelajaranList as $mapel)
                            <option value="{{ $mapel->id }}" @selected($mataPelajaranId == $mapel->id)>
                                {{ $mapel->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Realtime Search Bar --}}
            <div class="relative pt-1">
                <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    x-model="search"
                    placeholder="Ketik untuk mencari kode TP, deskripsi, KKTP (respons instan)..."
                    class="w-full rounded-xl border-gray-200 pl-10 pr-10 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500 py-2.5 shadow-sm"
                >
                <button
                    type="button"
                    x-show="search"
                    @click="search = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 hover:text-gray-600"
                >
                    ✕
                </button>
            </div>
        </div>

        {{-- CONTENT CONTAINER (KPI Stats + Subject Accordion Cards, updated via AJAX without reload) --}}
        <div class="relative" x-ref="daftarKomponen">
            {{-- Loading Shimmer Overlay --}}
            <div
                x-show="isLoading"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/70 backdrop-blur-xs min-h-[250px]"
            >
                <div class="flex items-center gap-2.5 rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-semibold text-white shadow-xl">
                    <svg class="h-4 w-4 animate-spin text-brand-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memperbarui data...</span>
                </div>
            </div>

            @include('portals.lembaga.akademik.komponen-penilaian._daftar')
        </div>
    </div>
</x-app-layout>
