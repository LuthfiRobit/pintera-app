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
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola penomoran slot belajar, mata pelajaran, dan pengampu untuk tiap kelas.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
            </p>
        </div>

        {{-- 1. Card Filter: Parameter Jadwal --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                <div>
                    <h2 class="font-display text-base font-bold text-gray-900">Filter Jadwal Pelajaran</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Pilih parameter tahun ajaran, kelas, dan semester untuk menampilkan data.</p>
                </div>
                @if ($kelasId && $semesterId)
                    <x-link-button href="{{ route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelasId, 'semester_id' => $semesterId]) }}" class="shrink-0 justify-center">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah Slot Jadwal
                    </x-link-button>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.jadwal-pelajaran.index') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 items-end">
                    <div>
                        <x-input-label value="Tahun Ajaran" />
                        <select name="tahun_ajaran_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Kelas" />
                        <select name="kelas_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Kelas —</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Semester" />
                        <select name="semester_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-2 sm:pt-0">
                        <x-primary-button type="submit" class="w-full justify-center h-10 shadow-sm transition duration-150">
                            Tampilkan
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>

        {{-- 2. Daftar Jadwal Pelajaran per Hari --}}
        @if ($kelasId && $semesterId)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
                    <div>
                        <h2 class="font-display text-base font-bold text-gray-900">Daftar Jadwal Pelajaran</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                        Total {{ $jadwalList->count() }} Sesi
                    </span>
                </div>

                @if ($jadwalList->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <x-icon name="event_busy" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
                        <p class="text-sm font-semibold text-gray-700">Belum Ada Jadwal Pelajaran</p>
                        <p class="text-xs text-gray-500 mt-1">Belum ada slot waktu dan mata pelajaran yang diatur untuk kelas dan semester ini.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 bg-white">
                        @foreach (\App\Enums\Hari::cases() as $hari)
                            @php $jadwalHariIni = $jadwalList->where('jamPelajaran.hari', $hari)->sortBy('jamPelajaran.urutan'); @endphp
                            @if ($jadwalHariIni->isNotEmpty())
                                <div>
                                    {{-- Section Hari --}}
                                    <div class="flex items-center justify-between bg-gray-50/75 px-6 py-3 border-y border-gray-100 mt-[-1px]">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="calendar_today" class="h-4 w-4 text-brand-500" />
                                            <span class="text-[12px] font-bold uppercase tracking-wider text-gray-700">{{ $hari->label() }}</span>
                                        </div>
                                        <span class="text-xs font-medium text-gray-500">{{ $jadwalHariIni->count() }} sesi</span>
                                    </div>

                                    {{-- Daftar Slot per Hari --}}
                                    <ul class="divide-y divide-gray-100">
                                        @foreach ($jadwalHariIni as $jadwal)
                                            <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-6 py-4 transition-colors duration-150 hover:bg-gray-50/60">
                                                <div class="flex flex-wrap items-center gap-3 md:gap-4">
                                                    {{-- Badge Waktu (Format tanpa detik: H:i) --}}
                                                    <div class="flex items-center gap-2 font-mono text-xs">
                                                        <span class="rounded bg-brand-50 px-2.5 py-1 font-bold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                                                            {{ substr($jadwal->jamPelajaran->jam_mulai, 0, 5) }}
                                                        </span>
                                                        <span class="text-gray-400 font-medium">&rarr;</span>
                                                        <span class="rounded bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/60">
                                                            {{ substr($jadwal->jamPelajaran->jam_selesai, 0, 5) }}
                                                        </span>
                                                    </div>

                                                    <span class="hidden md:inline text-gray-300">&bull;</span>

                                                    {{-- Badge Label Slot (Jam ke-1, Istirahat, dll) --}}
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200/60">
                                                        {{ $jadwal->jamPelajaran->label }}
                                                    </span>

                                                    <span class="hidden md:inline text-gray-300">&bull;</span>

                                                    {{-- Mata Pelajaran & Guru --}}
                                                    <div class="flex flex-wrap items-center gap-2 md:gap-3">
                                                        <span class="text-sm font-bold text-gray-900">
                                                            {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 sm:border-l sm:border-gray-200 sm:pl-3">
                                                            <x-icon name="person" class="h-3.5 w-3.5 text-gray-400" />
                                                            <span>Guru: <strong class="font-semibold text-gray-800">{{ $jadwal->guru->nama }}</strong></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>

