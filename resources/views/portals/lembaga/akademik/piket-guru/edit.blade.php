<x-app-layout>
    <div class="mx-auto max-w-lg space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Jadwal Piket Mingguan</h1>
            <a href="{{ route('admin.piket-guru.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Kembali</a>
        </div>

        <form method="POST" action="{{ route('admin.piket-guru.update', $jadwal) }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf @method('PUT')
            <div>
                <x-input-label value="Guru" />
                <select name="guru_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($guruList as $guru)
                        <option value="{{ $guru->id }}" @selected($guru->id === $jadwal->guru_id)>{{ $guru->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('guru_id')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Hari" />
                <select name="hari" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($nilai === $jadwal->hari)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('hari')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Tahun Ajaran & Semester" />
                <select name="semester_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($semesterList->groupBy(fn ($semester) => $semester->tahunAjaran->nama) as $namaTahunAjaran => $semesterGrup)
                        <optgroup label="{{ $namaTahunAjaran }}">
                            @foreach ($semesterGrup as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id', $jadwal->semester_id) == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Mengganti semester akan memindahkan jadwal piket harian yang sudah ter-generate ke semester baru (baris override manual/lampau/sudah dipakai tetap aman).</p>
                <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
            </div>
            <div class="flex justify-end pt-2">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
