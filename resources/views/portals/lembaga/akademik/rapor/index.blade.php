<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
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
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Tahun Ajaran" />
                        <x-select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 font-bold">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Pilih Semester" />
                        <x-select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 font-bold">
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Pilih Kelas" />
                        <x-select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 font-bold">
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
