<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
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
            <form method="GET" action="{{ route('admin.jadwal-pelajaran.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label value="Tahun Ajaran" />
                    <select name="tahun_ajaran_id" onchange="this.form.submit()" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Tahun Ajaran —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Kelas" />
                    <select name="kelas_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Semester" />
                    <select name="semester_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Semester —</option>
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
                @if ($kelasId && $semesterId)
                    <x-link-button variant="ghost" href="{{ route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelasId, 'semester_id' => $semesterId]) }}">
                        <span class="text-base leading-none">+</span> Tambah Slot
                    </x-link-button>
                @endif
            </form>
        </div>

        @if ($kelasId && $semesterId)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <ul class="divide-y divide-gray-100">
                    @forelse ($jadwalList as $jadwal)
                        <li class="px-5 py-3 text-sm text-gray-700">
                            {{ $jadwal->jamPelajaran->hari->label() }}, {{ $jadwal->jamPelajaran->jam_mulai }}–{{ $jadwal->jamPelajaran->jam_selesai }}
                            &middot; {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                            &middot; {{ $jadwal->guru->nama }}
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">Belum ada jadwal untuk kelas &amp; semester ini.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
