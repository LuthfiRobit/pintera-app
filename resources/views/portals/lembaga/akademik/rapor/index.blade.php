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
            <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
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
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Pilih Kelas" />
                        <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected($selectedKelas && $selectedKelas->id === $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Pilih Semester" />
                        <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="hasilRapor">
                @include('portals.lembaga.akademik.rapor._hasil')
            </div>
        </div>
    </div>
</x-app-layout>
