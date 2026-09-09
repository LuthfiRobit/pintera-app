<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-4">
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
                    <h1 class="font-display text-xl font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Rekapitulasi capaian nilai rapor siswa per kelas dan semester berdasarkan asesmen sumatif.
                </p>
            </div>
            <p class="text-xs text-gray-500 self-start sm:self-auto">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="raporFilter({
                tahunAjaranId: @js($tahunAjaranId),
                kelasId: @js($selectedKelas?->id),
                semesterId: @js($selectedSemester?->id),
                opsiUrl: @js(route('admin.rapor.opsi')),
                indexUrlBase: @js(route('admin.rapor.index')),
            })"
        >
            <!-- Filter Controls Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                {{-- Header of Filter --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3.5">
                    <div>
                        <h3 class="font-display text-sm font-bold text-gray-900">Filter Rekapitulasi Rapor</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Filter otomatis diperbarui secara instan tanpa reload halaman.</p>
                    </div>
                    <button
                        type="button"
                        x-show="tahunAjaranId || semesterId || kelasId"
                        @click="resetFilters()"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-800 transition self-start sm:self-auto"
                    >
                        <span>✕ Reset Semua Filter</span>
                    </button>
                </div>

                {{-- 3 Searchable Dropdown Filters --}}
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-600">Tahun Ajaran</label>
                        <x-select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-600">Pilih Semester</label>
                        <x-select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5">
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-600">Pilih Kelas</label>
                        <x-select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5">
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected($selectedKelas && $selectedKelas->id === $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>
            </div>

            <div x-ref="hasilRapor">
                @include('portals.lembaga.akademik.rapor._hasil')
            </div>
        </div>
    </div>
</x-app-layout>
