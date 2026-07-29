<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Komponen Penilaian</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="komponenPenilaianFilter({
                tahunAjaranId: @js($tahunAjaranId),
                semesterId: @js($semesterId),
                mataPelajaranId: @js($mataPelajaranId),
                search: @js($search),
                opsiUrl: @js(route('admin.komponen-penilaian.opsi')),
                indexUrlBase: @js(route('admin.komponen-penilaian.index')),
            })"
        >
            {{-- Filter Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative flex-1">
                        <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            x-model="search"
                            @input.debounce.400ms="muatUlangDaftar()"
                            placeholder="Cari kode TP atau deskripsi..."
                            class="w-full rounded-lg border-gray-200 pl-10 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500 py-2"
                        >
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="rounded-lg border-gray-200 text-sm text-gray-700 py-2">
                            <option value="">Semua Tahun Ajaran</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                        <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="rounded-lg border-gray-200 text-sm text-gray-700 py-2">
                            <option value="">Semua Semester</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        <select x-ref="mataPelajaranSelect" x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)" class="rounded-lg border-gray-200 text-sm text-gray-700 py-2">
                            <option value="">Semua Mata Pelajaran</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected($mataPelajaranId == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="daftarKomponen">
                @include('admin.komponen-penilaian._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
