<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jadwal — {{ $kelas->nama }}</h1>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.jadwal-pelajaran.index') }}" class="text-gray-500 hover:text-gray-700">Jadwal Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="POST" action="{{ route('admin.jadwal-pelajaran.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
                <input type="hidden" name="semester_id" value="{{ $semesterId }}">

                <div>
                    <x-input-label value="Jam Pelajaran" />
                    <select name="jam_pelajaran_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @forelse ($jamPelajaranList as $jam)
                            <option value="{{ $jam->id }}">{{ $jam->hari->label() }}, {{ $jam->jam_mulai }}–{{ $jam->jam_selesai }} ({{ $jam->label }})</option>
                        @empty
                            <option value="">Kelas ini belum punya Pola Jam — atur dulu di halaman Pola Jam</option>
                        @endforelse
                    </select>
                    <x-input-error :messages="$errors->get('jam_pelajaran_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Mata Pelajaran (opsional utk PAUD)" />
                    <select name="mata_pelajaran_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Tidak ada —</option>
                        @foreach ($mataPelajaranList as $mapel)
                            <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Guru" />
                    <select name="guru_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('guru_id')" class="mt-1.5" />
                </div>

                <x-primary-button type="submit">Simpan</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
