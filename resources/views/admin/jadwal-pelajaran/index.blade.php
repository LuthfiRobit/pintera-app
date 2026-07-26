<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="GET" action="{{ route('admin.jadwal-pelajaran.index') }}" class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto sm:min-w-48">
                        <x-input-label value="Tahun Ajaran" />
                        <select name="tahun_ajaran_id" onchange="this.form.submit()" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-auto sm:min-w-48">
                        <x-input-label value="Kelas" />
                        <select name="kelas_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Kelas —</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-auto sm:min-w-48">
                        <x-input-label value="Semester" />
                        <select name="semester_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-primary-button type="submit" class="w-full sm:w-auto justify-center">Tampilkan</x-primary-button>
                    </div>
                </div>

                @if ($kelasId && $semesterId)
                    <div class="mt-2 w-full sm:mt-0 sm:w-auto">
                        <x-link-button href="{{ route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelasId, 'semester_id' => $semesterId]) }}" class="w-full sm:w-auto justify-center">
                            <span class="text-base leading-none mr-1.5">+</span> Tambah Slot Jadwal
                        </x-link-button>
                    </div>
                @endif
            </form>
        </div>

        @if ($kelasId && $semesterId)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 bg-gray-50/50 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jadwal Pelajaran</p>
                </div>

                @if ($jadwalList->isEmpty())
                    <div class="px-5 py-12 text-center text-sm text-gray-500">Belum ada jadwal pelajaran yang diatur untuk kelas &amp; semester ini.</div>
                @else
                    <div class="divide-y divide-gray-200 bg-white">
                        @foreach (\App\Enums\Hari::cases() as $hari)
                            @php $jadwalHariIni = $jadwalList->where('jamPelajaran.hari', $hari)->sortBy('jamPelajaran.urutan'); @endphp
                            @if ($jadwalHariIni->isNotEmpty())
                                <div class="p-5">
                                    <p class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-brand-600">
                                        <x-icon name="calendar_today" class="h-4 w-4 text-brand-500" />
                                        {{ $hari->label() }}
                                    </p>
                                    <div class="space-y-2.5">
                                        @foreach ($jadwalHariIni as $jadwal)
                                            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/40 px-4 py-3 transition hover:bg-gray-50">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex h-11 w-28 shrink-0 flex-col items-center justify-center rounded-lg border border-gray-200 bg-white shadow-2xs">
                                                        <span class="font-mono text-xs font-bold text-gray-900">{{ $jadwal->jamPelajaran->jam_mulai }}–{{ $jadwal->jamPelajaran->jam_selesai }}</span>
                                                        <span class="text-[11px] font-medium text-gray-500">{{ $jadwal->jamPelajaran->label }}</span>
                                                    </div>
                                                    <div>
                                                        <p class="font-semibold text-gray-900">{{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}</p>
                                                        <p class="text-xs text-gray-500 mt-0.5">Guru: <span class="font-medium text-gray-700">{{ $jadwal->guru->nama }}</span></p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
