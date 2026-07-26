<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jadwal — {{ $kelas->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.jadwal-pelajaran.store') }}">
            @csrf
            <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
            <input type="hidden" name="semester_id" value="{{ $semesterId }}">

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700 border-b border-gray-100 pb-3">
                    <x-icon name="schedule" class="h-[15px] w-[15px] text-gray-400" />
                    Penempatan Slot &amp; Pengajar
                </p>

                <div>
                    <x-input-label value="Jam Pelajaran" />
                    <select name="jam_pelajaran_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="" disabled selected>— Pilih Slot Jam Pelajaran —</option>
                        @forelse ($jamPelajaranList as $jam)
                            <option value="{{ $jam->id }}" @selected(old('jam_pelajaran_id') == $jam->id)>{{ $jam->hari->label() }}, {{ $jam->jam_mulai }}–{{ $jam->jam_selesai }} ({{ $jam->label }})</option>
                        @empty
                            <option value="">Kelas ini belum punya Pola Jam — atur dulu di halaman Pola Jam</option>
                        @endforelse
                    </select>
                    <x-input-error :messages="$errors->get('jam_pelajaran_id')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Mata Pelajaran (opsional utk PAUD)" />
                        <select name="mata_pelajaran_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Tidak ada —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Guru Pengampu" />
                        <select name="guru_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled selected>— Pilih Guru —</option>
                            @foreach ($guruList as $guru)
                                <option value="{{ $guru->id }}" @selected(old('guru_id') == $guru->id)>{{ $guru->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('guru_id')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Jadwal</x-primary-button>
                <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
